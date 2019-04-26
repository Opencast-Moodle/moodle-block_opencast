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
 * Task for starting workflow to copy events.
 *
 * @package   block_opencast
 * @copyright 2018 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\task;

defined('MOODLE_INTERNAL') || die();

use tool_opencast\seriesmapping;

/**
 * Task for starting workflow to copy events.
 *
 * @package   block_opencast
 * @copyright 2018 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_duplicate_event extends \core\task\adhoc_task {

    /** @var int max number of retries for one task */
    const MAX_COUNT_RETRIES = 10;

    /**
     * Create a copy event task.
     */
    public function __construct() {
        $this->set_component('block_opencast');
    }

    /**
     * Start the copying workflow.
     *
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();

        // Adhoc task will be deleted by task manager after successful execution.
        // So abort - without an error - when course does not exist (no duplcation necessary anymore).
        $course = $DB->get_record('course', array('id' => $data->courseid));
        if (!$course) {
            mtrace("course to reset does not exist, ID: $data->courseid, deleting adhoc task.");
            return;
        }

        $a = clone ($data);
        $a->coursefullname = $course->fullname;
        $a->taskid = $this->get_id();

        // Test, whether opencast server is available.
        

        try {

            // Get duplication workflow.
            $duplicateworkflow = get_config('block_opencast', 'duplicateworkflow');
            if (empty($duplicateworkflow)) {
                throw new \moodle_exception('error_workflow_setup_missing', 'block_opencast');
            }
            // Add to string information for later use.
            $a->duplicateworkflow = $duplicateworkflow;

            // Series checks.
            if (empty($data->seriesid)) { //Should not happen as seriesid is checked during restore.
                throw new \moodle_exception('error_seriesid_taskdata_missing', 'block_opencast', '', $a);
            }

            // Check, whether seriesid of course exists...
            $apibridge = \block_opencast\local\apibridge::get_instance();
            if (!$seriesid = $apibridge->get_stored_seriesid($course->id)) {
                throw new \moodle_exception('error_seriesid_missing_course', 'block_opencast', '', $a);
            }

            // ...and matches the id of task.
            if ($seriesid != $data->seriesid) {
                throw new \moodle_exception('error_seriesid_not_matching', 'block_opencast', '', $a);
            }

            // Check, whether series exists in opencast system.
            $series = $apibridge->get_course_series($course->id);
            if (!isset($series)) {
                throw new \moodle_exception('error_seriesid_missing_opencast', 'block_opencast', '', $a);
            }

            // Event checks.
            // Intentionally no check, whether event exists on opencast system as customer required (May be done by the opencast).
            if (empty($data->eventid)) {
                throw new \moodle_exception('error_eventid_taskdata_missing', 'block_opencast', '', $a);
            }

            // Workflow checks.
            if (!$apibridge->check_if_workflow_exists($duplicateworkflow)) {
                throw new \moodle_exception('error_workflow_not_exists', 'block_opencast', '', $a);
            }

            $params = [
                'configuration' => json_encode((object) ['seriesID' => $data->seriesid])
            ];

            if (!$result = $apibridge->start_workflow($data->eventid, $duplicateworkflow, $params)) {
                throw new \moodle_exception('error_workflow_not_started', 'block_opencast', '', $a);
            }
        } catch (\Exception $e) {

            // Increase failure counter.
            $data->countfailed++;
            // Will be saved by \core\task\manager.
            $this->set_custom_data($data);

            // Re-throw exeption to keep task alive, if counter < MAX_COUNT_RETRIES.
            if ($data->countfailed < 10) {
                throw new \moodle_exception('errorduplicatetaskretry', 'block_opencast', '', $e->getMessage());
            } else {

                // Terminate (do not throw error) and notify admin.
                \block_opencast\local\notifications::notify_error('errorduplicatetaskterminate', $e);
            }
        }
    }

}
