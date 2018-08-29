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
 * @copyright 2018 Tobias Reischmann, WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_opencast\task;
use block_opencast\local\apibridge;

defined('MOODLE_INTERNAL') || die();

class process_delete_cron extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('processdelete', 'block_opencast');
    }

    public function execute() {
        global $DB;

        // Get all delete jobs.
        $sql = "SELECT * FROM {block_opencast_deletejob}";

        $jobs = $DB->get_records_sql($sql);

        if (!$jobs) {
            mtrace('...no jobs to proceed');
        }

        foreach ($jobs as $job) {
            mtrace('proceed: ' . $job->id);
            try {
                $apibridge = apibridge::get_instance();
                $event = $apibridge->get_opencast_video($job->opencasteventid);
                // If job failed previously and event does no longer exist, remove the delete job.
                if ($event->error == 404 & $job->failed) {
                    $DB->delete_records("block_opencast_deletejob", array('id' => $job->id));
                    mtrace('failed job ' . $job->id . ' removed');
                }
                // If deletion workflow finished, remove the video.
                if ($event->error == 0 &&
                    ($event->video->processing_state === "SUCCEEDED" ||
                        $event->video->processing_state === "PLANNED" )) {
                    $apibridge->delete_event($job->opencasteventid);
                    $DB->delete_records("block_opencast_deletejob", array('id' => $job->id));
                    mtrace('event ' . $job->opencasteventid . ' removed');
                }
                // Mark delete job as failed.
                if ($event->error !== 0) {
                    $job->failed = true;
                    $job->timemodified = time();
                    $DB->update_record("block_opencast_deletejob", $job);
                    mtrace('deletion of event ' . $job->opencasteventid . ' failed');
                }
            } catch (\moodle_exception $e) {
                mtrace('Job failed due to: ' . $e);
                $this->upload_failed($job, $e->getMessage());
            }
        }
    }
}