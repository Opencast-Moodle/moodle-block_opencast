<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event Visibility Helper.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use block_opencast_renderer;

/**
 * Event Visibility Helper.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visibility_helper {
    /** @var int visibility change failed */
    const STATUS_FAILED = 0;

    /** @var int visibility change is pendding */
    const STATUS_PENDING = 1;

    /** @var int visibility change completed */
    const STATUS_DONE = 2;

    /** @var int default waiting time in minutes */
    const DEFAULT_WAITING_TIME = 20;

    /**
     * Process all scheduled change visibility jobs.
     */
    public function cron() {
        global $DB;

        // Get the scheduled change visibility jobs with the pending status.
        $sql = "SELECT * FROM {block_opencast_visibility}" .
            " WHERE :now >= scheduledvisibilitytime AND status = :status";
        $params = [];
        $params['now'] = time();
        $params['status'] = self::STATUS_PENDING;
        $allscheduledvisibilityjobs = $DB->get_records_sql($sql, $params);

        if (empty($allscheduledvisibilityjobs)) {
            mtrace('...no scheduled change visibility jobs to proceed');
        }

        foreach ($allscheduledvisibilityjobs as $job) {
            mtrace('proceed: ' . $job->id);
            try {
                $this->process_scheduled_change_visibility_job($job);
            } catch (\moodle_exception $e) {
                mtrace('Event change visibility job failed due to: ' . $e);
            }
        }

        // Cleanup the visibility jobs.
        $sql = "SELECT * FROM {block_opencast_visibility}".
            " WHERE status = :status";
        $params = [];
        $params['status'] = self::STATUS_DONE;
        $alldonevisibilityjobs = $DB->get_records_sql($sql, $params);
        if (empty($alldonevisibilityjobs)) {
            mtrace('...no visibility jobs to cleanup');
        }

        foreach ($alldonevisibilityjobs as $job) {
            mtrace('cleaning-up: ' . $job->id);
            try {
                $this->cleanup_visibility_job($job);
            } catch (\moodle_exception $e) {
                mtrace('Cleanup visibility job failed due to: ' . $e);
            }
        }
    }

    /**
     * Processes the scheduled change visibility job.
     *
     * @param object $job represents the visibility job.
     *
     * @return boolean
     * @throws \moodle_exception
     */
    protected function process_scheduled_change_visibility_job($job) {
        $status = self::STATUS_FAILED;

        // Extract all the required parameters to perform the change_visibility function.
        list($ocinstanceid, $courseid, $eventidentifier) = $this->extract_job_params($job);
        // We check if there is any empty param.
        if (empty($ocinstanceid) || empty($courseid)) {
            mtrace('job ' . $job->id . ':(ERROR) Invalid parameters to perfomr the job.');
            self::change_job_status($job, $status);
            return;
        }

        $visibility = intval($job->scheduledvisibilitystatus);
        $groups = json_decode($job->scheduledvisibilitygroups);
        $apibridge = apibridge::get_instance($ocinstanceid);

        $allowedvisibilitystates = array(block_opencast_renderer::VISIBLE,
            block_opencast_renderer::HIDDEN, block_opencast_renderer::GROUP);

        if (!in_array($visibility, $allowedvisibilitystates)) {
            mtrace('job ' . $job->id . ':(ERROR) Has invalid visibility state.');
            self::change_job_status($job, $status);
            return;
        }

        // Check if Workflow is set and the acl control is enabled.
        if (get_config('block_opencast', 'workflow_roles_' . $ocinstanceid) == "" ||
            get_config('block_opencast', 'aclcontrolafter_' . $ocinstanceid) != true) {
            mtrace('job ' . $job->id . ':(ERROR) Invalid configuration to change visibility.');
            self::change_job_status($job, $status);
            return;
        }

        // Check if the teacher should be allowed to restrict the episode to course groups.
        $controlgroupsenabled = get_config('block_opencast', 'aclcontrolgroup_' . $ocinstanceid);
        if (!$controlgroupsenabled && $visibility == block_opencast_renderer::GROUP) {
            mtrace('job ' . $job->id . ':(ERROR) unable to control groups.');
            self::change_job_status($job, $status);
            return;
        }

        // Check if eventidentifier is not empty, if so, that means it is not yet uploaded.
        if (!empty($job->uploadjobid) && empty($eventidentifier)) {
            mtrace('job ' . $job->id . ':(PENDING) event identifier does not exists yet.');
            return;
        } else if (empty($job->uploadjobid) && empty($eventidentifier)) {
            // If both eventidentifier and uploadjobid are empty, that means it is faulty and needs to be deleted.
            mtrace('job ' . $job->id . ':(ERROR) no event identifier is set.');
            self::change_job_status($job, $status);
            return;
        }

        // Check if the video is still in processing mode.
        $eventobject = $apibridge->get_opencast_video($eventidentifier);
        if ($eventobject->error) {
            mtrace('job ' . $job->id . ':(ERROR) unable to find video.');
            self::change_job_status($job, $status);
            return;
        }
        $video = $eventobject->video;
        // We postpone the visibility change because an active workflow.
        if ($video->processing_state == 'RUNNING' || $video->processing_state == 'PAUSED') {
            mtrace('job ' . $job->id . ':(PENDING) there is an ongoning workflow processing.');
            return;
        }

        $visibilitychanged = $apibridge->change_visibility($eventidentifier, $courseid, $visibility, $groups);

        $jobmessage = '(ERROR) Changing visibility failed!';
        if ($visibilitychanged !== false) {
            $jobmessage = 'Visibility successfully changed';
            $status = self::STATUS_DONE;
        }

        mtrace('job ' . $job->id . ": $jobmessage");
        self::change_job_status($job, $status);
    }

    /**
     * Saves the change visibility job into db.
     *
     * @param object $visibility Visibility job object
     *
     * @return boolean the status creating the job.
     */
    public static function save_visibility_job($visibility) {
        global $DB;
        // Set the pending status.
        $visibility->status = self::STATUS_PENDING;
        if (!self::validate_job($visibility)) {
            return false;
        }
        // Save into db.
        return $DB->insert_record('block_opencast_visibility', $visibility);
    }

    /**
     * Updates the change visibility job in db.
     *
     * @param object $visibility Visibility job object
     *
     * @return boolean the status updating the job.
     */
    public static function update_visibility_job($visibility) {
        global $DB;
        // Set the pending status.
        $visibility->status = self::STATUS_PENDING;
        if (!self::validate_job($visibility)) {
            return false;
        }
        // Update the record in db.
        return $DB->update_record('block_opencast_visibility', $visibility);
    }

    /**
     * Deletes the change visibility job from db.
     *
     * @param object $visibility Visibility job object
     *
     * @return boolean the status deleting the job.
     */
    public static function delete_visibility_job($visibility) {
        global $DB;
        // Delete the visibility record.
        return $DB->delete_records('block_opencast_visibility', array('id' => $visibility->id));
    }

    /**
     * Validates the visibility job.
     *
     * @param object $visibility Visibility job object
     *
     * @return boolean whether the visibility is validated.
     */
    private static function validate_job($visibility) {
        $isvalid = true;

        // Make sure that, either uploadjobid exists or the other required params are set.
        if (empty($visibility->uploadjobid)) {
            if (empty($visibility->opencasteventid) || empty($visibility->ocinstanceid) || empty($visibility->courseid)) {
                $isvalid = false;
            }
        }

        // Make sure initialvisibilitystatus is set.
        if (!property_exists($visibility, 'initialvisibilitystatus')) {
            $isvalid = false;
        }

        // Make sure that scheduledvisibilitystatus is set, when the schedule time exists.
        if (!empty($visibility->scheduledvisibilitytime)) {
            if (!property_exists($visibility, 'scheduledvisibilitystatus')) {
                $isvalid = false;
            }
        }

        return $isvalid;
    }

    /**
     * Saves the change visibility job into db.
     *
     * @param object $job Visibility job object
     * @param int $status Visibility status.
     */
    public static function change_job_status($job, $status) {
        global $DB;
        $allowedjobstatus = array(self::STATUS_PENDING, self::STATUS_DONE,
            self::STATUS_FAILED);

        if (!in_array($status, $allowedjobstatus)) {
            throw new \coding_exception('Invalid job status code.');
        }
        // Set the pending status.
        $job->status = $status;
        // Save into db.
        $DB->update_record('block_opencast_visibility', $job);
    }

    /**
     * Return the Visibility object containing ACL roles based on initial visibility configs.
     *
     * @param \stdClass $uploadjob upload job to be checked
     * @return \stdClass $initialvisibility initial visibility object.
     * @throws \dml_exception A DML specific exception is thrown for any errors.
     */
    public static function get_initial_visibility($uploadjob) {
        global $DB;
        // Get the visibility record.
        $visibilityrecord = $DB->get_record('block_opencast_visibility', array('uploadjobid' => $uploadjob->id));
        // Initialize the visibility as Visible.
        $visibility = block_opencast_renderer::VISIBLE;

        // Prepare the variables to be used throughout the process.
        $groups = null;

        // If the visibility record is available, we get the data from it.
        if ($visibilityrecord) {
            // Set the inital visibility.
            $visibility = intval($visibilityrecord->initialvisibilitystatus);

            // Get the initial groups if exists.
            if (!empty($visibilityrecord->initialvisibilitygroups)) {
                $groups = json_decode($visibilityrecord->initialvisibilitygroups, true);
            }

            // Checking the visibility value against the allowed visibility states.
            $allowedvisibilitystates = array(block_opencast_renderer::VISIBLE,
                block_opencast_renderer::HIDDEN, block_opencast_renderer::GROUP);

            if (!in_array($visibility, $allowedvisibilitystates)) {
                throw new \coding_exception('Invalid visibility state.');
            }
        }

        // Get all related acls.
        $acls = self::get_acl_roles($uploadjob, $visibility, $groups);
        // Create an object to be consumed later.
        $initialvisibility = new \stdClass();
        $initialvisibility->roles = $acls;

        return $initialvisibility;
    }

    /**
     * Gets acls for the initial visibility of an upload job, based on requested initial visibility.
     *
     * @param \stdClass $uploadjob upload job to be checked.
     * @param int $visibility the initial visibility state.
     * @param array $groups the initial groups.
     * @return array $acls initial acls.
     * @throws \dml_exception A DML specific exception is thrown for any errors.
     */
    private static function get_acl_roles($uploadjob, $visibility, $groups) {
        // Retrieve required values from upload job object.
        $courseid = $uploadjob->courseid;
        $ocinstanceid = $uploadjob->ocinstanceid;
        // Get apibridge instance based on job oc instance.
        $apibridge = apibridge::get_instance($ocinstanceid);
        // Get all configured roles.
        $roles = $apibridge->getroles();
        // In case of hidden visibility, only permanenet roles will be set.
        if ($visibility == block_opencast_renderer::HIDDEN) {
            // Get permanenet roles only.
            $roles = $apibridge->getroles(1);
        }
        // Initialize acl empty array.
        $acls = [];
        switch ($visibility) {
            case block_opencast_renderer::VISIBLE:
            case block_opencast_renderer::HIDDEN:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        $rolenameformatted = $apibridge::replace_placeholders($role->rolename,
                            $courseid, null, $uploadjob->userid)[0];
                        if ($rolenameformatted) {
                            $acls[] = (object)array(
                                'allow' => true,
                                'action' => $action,
                                'role' => $rolenameformatted,
                            );
                        }
                    }
                }
                break;
            case block_opencast_renderer::GROUP:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        foreach ($apibridge::replace_placeholders($role->rolename,
                            $courseid, $groups, $uploadjob->userid) as $rule) {
                            if ($rule) {
                                $acls[] = (object)array(
                                    'allow' => true,
                                    'action' => $action,
                                    'role' => $rule,
                                );
                            }
                        }
                    }
                }
                break;
            default:
                throw new \coding_exception('The provided visibility status is not valid!');
        }

        return $acls;
    }

    /**
     * Performs a cleanup operation on the visibility job.
     *
     * @param object $job represents the visibility job.
     * @throws \moodle_exception
     */
    protected function cleanup_visibility_job($job) {
        global $DB;
        // We will change back the status if the job got a date in future.
        if (!empty($job->scheduledvisibilitytime) && intval($job->scheduledvisibilitytime) >= time()) {
            mtrace('job ' . $job->id . ":(ERROR) The job has been scheduled in future and cannot be cleaned." .
                " Setting status back to pending.");
            self::change_job_status($job, self::STATUS_PENDING);
            return;
        }

        // Delete the visibility record otherwise.
        $DB->delete_records('block_opencast_visibility', array('id' => $job->id));
        mtrace('job ' . $job->id . ' removed');
    }

    /**
     * Get the required parameters for performing scheduled visibility changes.
     * Assuming that the visibility job now handles both uploadjobs and normal events,
     * therefore to extract all required parameters when need to check for both!
     *
     * @param object $job Visibility job object
     * @return array the parameters for performing scheduled visibility changes
     */
    private function extract_job_params($job) {
        global $DB;
        $ocinstanceid = null;
        $courseid = null;
        $opencasteventid = null;
        // First, we look for the uploadjobid, if it is set it means the visibility job is from uploading process.
        if (!empty($job->uploadjobid)) {
            $uploadjob = $DB->get_record('block_opencast_uploadjob', ['id' => $job->uploadjobid]);
            $ocinstanceid = $uploadjob->ocinstanceid;
            $courseid = $uploadjob->courseid;
            $opencasteventid = $uploadjob->opencasteventid;
        }

        // Next, we look for the ocinstanceid, if it is not set yet and this job has it, we take it.
        if (empty($ocinstanceid) && !empty($job->ocinstanceid)) {
            $ocinstanceid = $job->ocinstanceid;
        }
        // Next, we look for the courseid, if it is not set yet and this job has it, we take it.
        if (empty($courseid) && !empty($job->courseid)) {
            $courseid = $job->courseid;
        }
         // Next, we look for the eventidentifier, if it is not set yet and this job has it, we take it.
        if (empty($opencasteventid) && !empty($job->opencasteventid)) {
            $opencasteventid = $job->opencasteventid;
        }

        // Finally, we return the array of parameters.
        return array($ocinstanceid, $courseid, $opencasteventid);
    }

    /**
     * Returns scheduled change visibility waiting time.
     *
     * @param int $ocinstanceid The opencast instance id.
     * @param array $customminutes Custome minutes to be added or deducted on demand.
     * @return int
     */
    public static function get_waiting_time($ocinstanceid, $customminutes = []) {
        $configwaitingtime = get_config('block_opencast', 'aclcontrolwaitingtime_' . $ocinstanceid);
        if (empty($configwaitingtime)) {
            $configwaitingtime = self::DEFAULT_WAITING_TIME;
        }
        $waitingtime = time() + (intval($configwaitingtime) * 60);
        // Apply custom minute difference.
        if (isset($customminutes['minutes']) && $customminutes['minutes']) {
            $minutes = $customminutes['minutes'];
            $action = isset($customminutes['action']) ? $customminutes['action'] : 'plus';
            switch ($action) {
                case 'minus':
                    $waitingtime -= ($minutes * 60);
                    break;
                case 'plus':
                default:
                    $waitingtime += ($minutes * 60);
            }
        }
        return array($waitingtime, $configwaitingtime);
    }

    /**
     * Get the current scheduled visibility info for an event if any.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid Course id
     * @param string $opencasteventid event identifier
     *
     * @return ?object The current scheduled visibility info, or null if not found.
     */
    public static function get_event_scheduled_visibility($ocinstanceid, $courseid, $opencasteventid) {
        global $DB;
        // Now that we have two different options in visibility table, we need to prepare a comprehensive sql.
        // Assuming that the visibility was requested by changevisibility form, not the addvideo (not uploadjob).
        $select = "SELECT * FROM {block_opencast_visibility}";
        $params = array(
            'ocinstanceid' => $ocinstanceid,
            'courseid' => $courseid,
            'opencasteventid' => $opencasteventid,
        );
        $where = array(
            'ocinstanceid = :ocinstanceid',
            'courseid = :courseid',
            'opencasteventid = :opencasteventid',
        );
        $sql = $select . ' WHERE ' . implode(' AND ', $where);
        $visibility = $DB->get_record_sql($sql, $params);
        // If the record exists already, we return it. Otherwise, we will give it another chance with uploadjobid.
        if (!empty($visibility)) {
            return $visibility;
        }
        // However, here we look to see if the uploadjob for that event exists.
        $uploadjob = $DB->get_record('block_opencast_uploadjob', $params);
        if (!empty($uploadjob)) {
            $params = array(
                'uploadjobid' => intval($uploadjob->id),
            );
            $where = array(
                'uploadjobid = :uploadjobid',
            );
        }
        $sql = $select . ' WHERE ' . implode(' AND ', $where);
        $visibility = $DB->get_record_sql($sql, $params);
        return !empty($visibility) ? $visibility : null;
    }

    /**
     * Get the current scheduled visibility info for an uploadjob if any.
     *
     * @param int $uploadjobid the upload job id
     * @param boolean $onlyscheduled whether to only for scheduled visibility.
     *
     * @return ?object The current scheduled visibility job info, or null if not found.
     */
    public static function get_uploadjob_scheduled_visibility($uploadjobid, $onlyscheduled = true) {
        global $DB;
        $sql = "SELECT * FROM {block_opencast_visibility}" .
            " WHERE uploadjobid = :uploadjobid";
        $params = array(
            'uploadjobid' => intval($uploadjobid),
        );
        if ($onlyscheduled) {
            $sql .= " AND scheduledvisibilitytime IS NOT NULL";
        }
        $visibility = $DB->get_record_sql($sql, $params);
        return !empty($visibility) ? $visibility : null;
    }
}
