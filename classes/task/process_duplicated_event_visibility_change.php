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
 * Task to change the visibility of the duplicated event
 *
 * @package     block_opencast
 * @copyright   2023 Farbod Zamani Boroujeni, ELAN e.V.
 * @author      Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\task;

use block_opencast\local\apibridge;
use block_opencast\local\notifications;
use core\task\adhoc_task;
use Exception;
use moodle_exception;

/**
 * Task to change the visibility of the duplicated event
 *
 * @package     block_opencast
 * @copyright   2023 Farbod Zamani Boroujeni, ELAN e.V.
 * @author      Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_duplicated_event_visibility_change extends adhoc_task {


    /** @var int max number of failed retries for one task */
    const MAX_COUNT_FAILS = 10;
    /** @var int max number of pending retries for one task */
    const MAX_COUNT_PENDING = 240;
    /** @var int max number of minutes to wait before next run */
    const WAITING_INTERVALS_MINUTES = 1;
    /** @var int task is completed */
    const TASK_COMPLETED = 1;
    /** @var int task is in pending and waits to re-reun */
    const TASK_PENDING = 2;
    /** @var int task failed */
    const TASK_FAILED = 0;

    /**
     * Create a copy event task.
     */
    public function __construct() {
        $this->set_component('block_opencast');
    }

    /**
     * Start adopting visibility.
     *
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();

        $course = $DB->get_record('course', ['id' => $data->courseid]);
        // If no course, we abandon the task to get it deleted by task manager.
        if (!$course) {
            mtrace("course to reset does not exist, ID: $data->courseid, deleting adhoc task.");
            return;
        }

        $a = clone($data);
        // Adding notification required parameters.
        $a->coursefullname = $course->fullname;
        $a->taskid = $this->get_id();

        $visiblitychangestatus = self::TASK_PENDING;

        try {
            $apibridge = apibridge::get_instance($data->ocinstanceid);
            // Opencast Workflow id check.
            if (empty($data->ocworkflowid) || !is_number($data->ocworkflowid)) {
                $visiblitychangestatus = self::TASK_FAILED;
                throw new moodle_exception('error_no_duplicate_workflow_id', 'block_opencast', '', $a);
            }
            // Event checks.
            if (empty($data->sourceeventid)) {
                $visiblitychangestatus = self::TASK_FAILED;
                throw new moodle_exception('error_no_duplicate_origin_event_id', 'block_opencast', '', $a);
            }

            // Extract duplicated event id, if it is not yet there.
            if (empty($data->duplicatedeventid)) {
                $data->duplicatedeventid = $apibridge->get_duplicated_episodeid($data->ocworkflowid);
            }

            // Repeating the task a few time until it gets or rejects.
            if (empty($data->duplicatedeventid)) {
                throw new moodle_exception('error_duplicated_event_id_not_ready', 'block_opencast', '', $a);
            }

            // Ensure video processing is finished and no other workflow is running.
            $event = $apibridge->get_already_existing_event([$data->duplicatedeventid]);
            if (!$event || !in_array($event->status, ['EVENTS.EVENTS.STATUS.PROCESSED', 'EVENTS.EVENTS.STATUS.PROCESSING_FAILURE'])
                || count($event->publication_status) == 0
                || count($event->publication_status) == 1 && $event->publication_status[0] === 'internal') {
                throw new moodle_exception('error_duplicated_event_id_not_ready', 'block_opencast', '', $a);
            }

            // Adopting the visibility.
            $visiblitychangestatus = $apibridge->set_duplicated_event_visibility(
                $data->duplicatedeventid,
                $data->sourceeventid,
                $data->courseid
            );
            if ($visiblitychangestatus !== self::TASK_COMPLETED) {
                throw new moodle_exception('error_duplicated_event_visibility_change', 'block_opencast', '', $a);
            }

        } catch (Exception $e) {
            $retry = true;
            if ($visiblitychangestatus === self::TASK_PENDING) {
                if (isset($data->countpending)) {
                    $data->countpending++;
                } else {
                    $data->countpending = 1;
                }
                if ($data->countpending >= self::MAX_COUNT_PENDING) {
                    $retry = false;
                }
            } else if ($visiblitychangestatus === self::TASK_FAILED) {
                if (isset($data->countfailed)) {
                    $data->countfailed++;
                } else {
                    $data->countfailed = 1;
                }
                if ($data->countfailed >= self::MAX_COUNT_FAILS) {
                    $retry = false;
                }
            }

            if ($retry) {
                $interval = self::WAITING_INTERVALS_MINUTES;
                $intervalstring = "+$interval min" . ($interval > 1 ? 's' : '');
                $futuretime = strtotime($intervalstring);
                $this->set_next_run_time($futuretime);
                $this->set_custom_data($data);
                throw new moodle_exception('errorduplicatedeventvisibilitytaskretry', 'block_opencast', '', $e->getMessage());
            } else {
                notifications::notify_error('errorduplicatedeventvisibilitytaskterminated', $e);
            }
        }
    }
}
