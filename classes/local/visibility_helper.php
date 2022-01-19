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

defined('MOODLE_INTERNAL') || die();

use block_opencast_renderer;

/**
 * Event Visibility Helper.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class visibility_helper
{
    /** @var int visibility change failed */
    const STATUS_FAILED = 0;

    /** @var int visibility change is pendding */
    const STATUS_PENDING = 1;

    /** @var int visibility change completed */
    const STATUS_DONE = 2;

    /** @var int default waiting time in minutes*/
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
        global $DB;
        $status = self::STATUS_FAILED;

        // Prepare all the required variables to perform the change_visibility function.
        $uploadjob = $DB->get_record('block_opencast_uploadjob', ['id' => $job->uploadjobid]);
        $ocinstanceid = $uploadjob->ocinstanceid;
        $courseid = $uploadjob->courseid;
        $eventidentifier = $uploadjob->opencasteventid;
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
     * @param object $visibility Visibility object
     */
    public static function save_visibility_job($visibility) {
        global $DB;
        // Set the pending status.
        $visibility->status = self::STATUS_PENDING;
        // Save into db.
        $DB->insert_record('block_opencast_visibility', $visibility);
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
        $visibilityjob = null;

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

            // Providing the visibility job record to be used later on.
            $visibilityjob = $visibilityrecord;
        }

        // Get all related acls.
        $acls = self::get_acl_roles($uploadjob, $visibility, $groups);
        // Create an object to be consumed later.
        $initialvisibility = new \stdClass();
        $initialvisibility->roles = $acls;
        $initialvisibility->groups = $groups;
        $initialvisibility->visibilityjob = $visibilityjob;

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
        // Initialize acl empty array.
        $acls = [];
        switch ($visibility) {
            case block_opencast_renderer::VISIBLE:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        $rolenameformatted = $apibridge::replace_placeholders($role->rolename, $courseid, null, $uploadjob->userid)[0];
                        if ($rolenameformatted) {
                            $acls [] = (object)array(
                                'allow' => true,
                                'action' => $action,
                                'role' => $rolenameformatted,
                            );
                        }
                    }
                }
                break;
            case block_opencast_renderer::HIDDEN:
                break;
            case block_opencast_renderer::GROUP:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        foreach ($apibridge::replace_placeholders($role->rolename, $courseid, $groups, $uploadjob->userid) as $rule) {
                            if ($rule) {
                                $acls [] = (object)array(
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
}
