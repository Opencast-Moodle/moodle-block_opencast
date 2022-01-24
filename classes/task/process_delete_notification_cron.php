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
 * @package   block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\task;

use tool_opencast\local\settings_api;

/**
 * Task for deleting the event status notification jobs.
 *
 * If a notification job remains in the list without any status change for at least a day,
 * means there was an error in between and the job should be deleted.
 *
 * @package block_opencast
 */
class process_delete_notification_cron extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('processdeletenotification', 'block_opencast');
    }

    public function execute() {
        global $DB;

        $ocinstances = settings_api::get_ocinstances();
        foreach ($ocinstances as $ocinstance) {

            // Get admin setting for deleting notification jobs.
            $notificationdeletionconfig = get_config('block_opencast', 'eventstatusnotificationdeletion_' . $ocinstance->id);
            $notificationdeletionconfig = intval($notificationdeletionconfig);

            // If the config is zero, we consider it as disable.
            if (!is_numeric($notificationdeletionconfig) || $notificationdeletionconfig == 0) {
                mtrace('...not configured');
                continue;
            }

            // Initialize the days.
            $deleteindays = 1;
            // The config is only acceptable if it is an integer more than 1.
            if (is_numeric($notificationdeletionconfig) && $notificationdeletionconfig > 1) {
                $deleteindays = $notificationdeletionconfig;
            }

            // Create formatting string.
            $timeformatstring = '+' . $deleteindays . ' day' . (($deleteindays > 1) ? 's' : '');

            // Get all waiting notification jobs.
            $allnotificationjobs = $DB->get_records('block_opencast_notifications',
                array('ocinstanceid' => $ocinstance->id), 'timecreated DESC');

            if (!$allnotificationjobs) {
                mtrace('...no jobs to proceed');
                continue;
            }

            foreach ($allnotificationjobs as $job) {
                mtrace('proceed: ' . $job->id);
                try {
                    // Get the deadline timestamp based on creation time of the job.
                    $expirytime = strtotime($timeformatstring, $job->timecreated);

                    // Considering time notified if it is set.
                    if (!empty($job->timenotified)) {
                        $expirytime = strtotime($timeformatstring, $job->timenotified);
                    }

                    // Check if the job deadline time is up.
                    if (time() > $expirytime) {
                        $DB->delete_records("block_opencast_notifications",
                            array('id' => $job->id, 'ocinstanceid' => $ocinstance->id));
                        mtrace('job ' . $job->id . ' deleted.');
                    }
                } catch (\moodle_exception $e) {
                    mtrace('Job failed due to: ' . $e);
                }
            }
        }
    }
}
