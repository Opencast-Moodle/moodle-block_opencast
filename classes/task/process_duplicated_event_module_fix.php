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
 * Adhoc task to check and fix episode modules in the newly imported course.
 *
 * @package     block_opencast
 * @copyright   2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author      Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\task;

use block_opencast\local\apibridge;
use block_opencast\local\notifications;
use block_opencast\local\importvideosmanager;
use core\task\adhoc_task;
use moodle_exception;

/**
 * Adhoc task to check and fix episode modules in the newly imported course.
 *
 * @package     block_opencast
 * @copyright   2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author      Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_duplicated_event_module_fix extends adhoc_task {

    /** @var int max number of pending retries for one task */
    const MAX_COUNT_PENDING = 20;

    /**
     * Create a fix module task.
     */
    public function __construct() {
        $this->set_component('block_opencast');
    }

    /**
     * Start fixing episode modules.
     *
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();

        // Get the import mapping record.
        $mapping = importvideosmanager::get_import_mapping_record(['id' => $data->mappingid]);
        if (empty($mapping)) {
            mtrace("import mapping to fix does not exist, ID: {$data->mappingid}, deleting adhoc task.");
            return;
        }

        // Do the cleanup if the import mapping record is either failed or succeeded.
        if ($mapping->status != importvideosmanager::MAPPING_STATUS_PENDING) {
            $deleted = importvideosmanager::delete_import_mapping_record(['id' => $mapping->id]);
            $logmessage = "The import mapping record for event id: {$mapping->sourceeventid}" .
                " with ocworkflowid: {$mapping->ocworkflowid} & duplicated event id: {$data->duplicatedeventid}" .
                " in course (ID: {$mapping->targetcourseid}) after {$mapping->attemptcount} attempt(s) %s";
            $status = 'was successful';
            if ($mapping->status == importvideosmanager::MAPPING_STATUS_FAILED) {
                $status = 'failed';
            }
            $status .= $deleted ? ' and has been deleted.' : ' and could not be deleted.';
            mtrace(sprintf($logmessage, $status));
            return;
        }

        if (!importvideosmanager::validate_mapping_record($mapping)) {
            mtrace("invalid import mapping record, ID: {$data->mappingid}, deleting adhoc task");
            return;
        }

        $course = $DB->get_record('course', ['id' => $mapping->targetcourseid]);
        $a = clone($data);
        // Adding notification required parameters.
        $a->coursefullname = $course->fullname;
        $a->taskid = $this->get_id();

        try {
            // Make sure the restore session is completed.
            if ($mapping->restorecompleted != importvideosmanager::RESTORE_STATUS_COMPLETED) {
                // If not, throw the error and repeat the task.
                throw new moodle_exception('restore_still_in_progress', 'block_opencast', '', $a);
            }

            $apibridge = apibridge::get_instance($mapping->ocinstanceid);
            if (empty($data->duplicatedeventid)) {
                $data->duplicatedeventid = $apibridge->get_duplicated_episodeid($mapping->ocworkflowid);
            }
            // Repeating the task a few time until it gets or rejects.
            if (empty($data->duplicatedeventid)) {
                throw new moodle_exception('importmapping_no_duplicated_event_id_yet', 'block_opencast', '', $a);
            }

            // Perform the episode modules fix.
            importvideosmanager::fix_imported_episode_modules_in_new_course($mapping, $data->duplicatedeventid);
            $mapping->status = importvideosmanager::MAPPING_STATUS_SUCCESS;
            importvideosmanager::update_import_mapping_record($mapping);
        } catch (moodle_exception $e) {
            $mapping->attemptcount = intval($mapping->attemptcount) + 1;
            importvideosmanager::update_import_mapping_record($mapping);
            // Check whether to retry.
            if ($mapping->attemptcount <= self::MAX_COUNT_PENDING) {
                $this->set_custom_data($data);
                throw new moodle_exception('importmapping_modulesfixtaskretry', 'block_opencast', '', $e->getMessage());
            } else {
                $mapping->status = importvideosmanager::MAPPING_STATUS_FAILED;
                importvideosmanager::update_import_mapping_record($mapping);
                notifications::notify_error('importmapping_modulesfixtasktermination', $e);
            }
        }

        // By the time we hit here and the process is not pending,
        // we proceed with one last iteration to cleanup the import mapping record.
        if ($mapping->status != importvideosmanager::MAPPING_STATUS_PENDING) {
            $this->set_next_run_time(strtotime("+1 min"));
            $this->set_custom_data($data);
            throw new moodle_exception('importmapping_modulesfixtaskretry', 'block_opencast', '', 'performing cleanup repeat...');
        }
    }
}
