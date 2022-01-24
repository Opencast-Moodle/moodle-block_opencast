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
 * Event Process Notification Helper.
 * @package    block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

/**
 * Event Process Notification Helper.
 * @package    block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class eventstatus_notification_helper {

    /**
     * Save the event status notification job onto the db table to be processed later with cronjobs.
     *
     * @param string $eventidentifier
     * @param int $courseid Course id
     * @param int $uploaderuserid userid of the uploader
     */
    public static function save_notification_jobs($ocinstanceid, $eventidentifier, $courseid, $uploaderuserid) {
        global $DB;

        // Initialize the notification job.
        $job = new \stdClass();
        $job->ocinstanceid = $ocinstanceid;
        $job->opencasteventid = $eventidentifier;
        $job->courseid = $courseid;
        $job->userid = $uploaderuserid;
        $job->status = 'RUNNING';
        $job->notified = 0;
        $job->timecreated = time();
        // Insert the notification job into db.
        $DB->insert_record('block_opencast_notifications', $job);
    }

    /**
     * Process all transfers to opencast server.
     */
    public function cron() {
        global $DB;

        // Get all waiting notification jobs.
        $allnotificationjobs = $DB->get_records('block_opencast_notifications', array(), 'timecreated ASC');

        if (!$allnotificationjobs) {
            mtrace('...no notification jobs to proceed');
        }

        foreach ($allnotificationjobs as $job) {
            mtrace('proceed: ' . $job->id);
            try {
                $this->process_notification_job($job);
            } catch (\moodle_exception $e) {
                mtrace('Notification Job failed due to: ' . $e);
            }
        }
    }

    /**
     * Processes the notification job.
     * It gets the current event process status from Opencast, and then notifies corresponding users.
     * If the SUCCEEDED or FAILED Status is recognized the job will be deleted from the list after being notified.
     *
     * @param object $job represents the notification job.
     *
     * @return boolean
     * @throws \moodle_exception
     */
    protected function process_notification_job($job) {
        global $DB;
        $ocinstanceid = $job->ocinstanceid;
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get admin config, whether to send notification or not.
        $notificationenabled = get_config('block_opencast', 'eventstatusnotificationenabled_' . $ocinstanceid);

        // If the job status is FAILED or SUCCEEDED and it has already been notified or the config is not enabled,
        // we remove the job because it is completed.
        if (($job->status == 'FAILED' || $job->status == 'SUCCEEDED') && ($job->notified == 1 || !$notificationenabled)) {
            $DB->delete_records("block_opencast_notifications", array('id' => $job->id));
            mtrace('job ' . $job->id . ' completed and deleted.');
            return;
        }

        // Get the video status from Opencast.
        $eventobject = $apibridge->get_opencast_video($job->opencasteventid);
        $video = $eventobject->video;

        // If the video is not available anymore, we just print it out.
        if (!$video) {
            mtrace('job ' . $job->id . ' deleted due to unavailable video.');
            return;
        }

        // If there is a new status in Opencast, we update the job status and prepare to send notification.
        if ($job->status != $video->processing_state) {
            $job->status = $video->processing_state;
            // Notified flag prevents the system from sending several notifications.
            $job->notified = 0;
        }

        // If the job is already notified, there was no changes to the status but the job is still pending.
        // We consider FAILED or SUCCEEDED status as a breakpoint.
        if ($job->notified) {
            mtrace('job ' . $job->id . ' is pending: nothing to notify yet.');
            return;
        }

        // If the config is enabled and the job is not already notified.
        if ($notificationenabled && $job->notified == 0) {
            $this->notify_users($job, $video);
            $job->notified = 1;
            $job->timenotified = time();
            mtrace('job ' . $job->id . ' notified.');
        }

        // Update job for further actions and decisions.
        $DB->update_record('block_opencast_notifications', $job);
    }

    /**
     * Notify users about the event process status.
     * Preparing the userlists by checking agains admin setting.
     * Preparting the message status text.
     * Send notification using notification class.
     *
     * @param object $job represents the notification job.
     * @param object $video the video object retrieved from Opencast.
     *
     */
    private function notify_users($job, $video) {
        global $DB;
        // Initialize the user list as an empty array.
        $usertolist = [];
        // Add uploader user object to the user list.
        $usertolist[] = \core_user::get_user($job->userid);

        // Get admin config to check if all teachers of the course should be notified as well.
        $notifyteachers = get_config('block_opencast', 'eventstatusnotifyteachers_' . $job->ocinstanceid);
        if ($notifyteachers) {
            // Get the role of teachers.
            $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
            // Get the course context.
            $context = \context_course::instance($job->courseid);
            // Get the teachers based on their role in the course context.
            $teachers = get_role_users($role->id, $context);

            // If teachers array list is not empty, we add them to the user list.
            if (!empty($teachers)) {
                foreach ($teachers as $teacher) {
                    // We need to make sure that the uploader is not in the teachers list.
                    if ($teacher->id != $job->userid) {
                        $usertolist[] = $teacher;
                    }
                }
            }
        }

        // Get status message text based on the status code.
        $statusmessage = $this->get_status_message($job->status);

        // Notify users one by one.
        foreach ($usertolist as $userto) {
            notifications::notify_event_status($job->courseid, $userto, $statusmessage, $video);
        }
    }

    /**
     * Get the relative status text based on status code.
     *
     * @param string $status status string code.
     * @return string status message text.
     */
    private function get_status_message($status) {
        switch ($status) {
            case 'FAILED' :
                return get_string('ocstatefailed', 'block_opencast');
            case 'PLANNED' :
                return get_string('planned', 'block_opencast');
            case 'CAPTURING' :
                return get_string('ocstatecapturing', 'block_opencast');
            case 'NEEDSCUTTING' :
                return get_string('ocstateneedscutting', 'block_opencast');
            case 'DELETING' :
                return get_string('deleting', 'block_opencast');
            case 'RUNNING' :
            case 'PAUSED' :
                return get_string('ocstateprocessing', 'block_opencast');
            case 'SUCCEEDED' :
            default :
                return get_string('ocstatesucceeded', 'block_opencast');
        }
    }
}
