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
 * Testcase for backup and restore of block_opencast.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

use tool_opencast\seriesmapping;

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/lib/cronlib.php');
require_once($CFG->dirroot . '/blocks/opencast/tests/helper/apibridge_testable.php');

/**
 * Testcase for backup and restore of block_opencast.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_backup_testcase extends advanced_testcase {

    /** var string apiurl for the testcase, must NOT be a real server! */
    private $apiurl = 'server.opencast.testcase';

    public function setUp() {
        parent::setUp();
        \block_opencast\local\apibridge::set_testing(true);
    }

    public function tearDown() {
        parent::tearDown();
        \block_opencast\local\apibridge::set_testing(false);
    }

    private function get_backup_filepath($courseid) {
        global $CFG;
        return $CFG->tempdir . '/backup/core_course_testcase_' . $courseid;
    }

    private function get_backup_filename($courseid, $blockid) {

        $backupfilepath = $this->get_backup_filepath($courseid);
        return $backupfilepath . '/course/blocks/opencast_' . $blockid . '/opencast.xml';
    }

    /**
     * Backup a course and return its backup ID.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user doing the backup.
     * @return string filepath of backup
     */
    protected function backup_course($courseid, $includevideos = false, $userid = 2) {

        $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO, backup::MODE_AUTOMATED, $userid);
        foreach ($bc->get_plan()->get_settings() as $setting) {
            if ($setting instanceof \backup_block_opencast_setting) {
                $setting->set_value($includevideos);
            }
        }
        $bc->execute_plan();

        $results = $bc->get_results();

        $packer = get_file_packer('application/vnd.moodle.backup');
        $results['backup_destination']->extract_to_pathname($packer, $this->get_backup_filepath($courseid));

        $bc->destroy();
        unset($bc);

        return 'core_course_testcase_' . $courseid;
    }

    /**
     * Restore a course.
     *
     * @param int $backupid The backup ID.
     * @param int $courseid The course ID to restore in, or 0.
     * @param int $userid The ID of the user performing the restore.
     * @return stdClass The updated course object.
     */
    protected function restore_course($backupid, $courseid, $includevideos, $userid) {
        global $DB;

        $target = backup::TARGET_CURRENT_ADDING;
        if (!$courseid) {
            $target = backup::TARGET_NEW_COURSE;
            $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
            $courseid = restore_dbops::create_new_course('Tmp', 'tmp', $categoryid);
        }

        $rc = new restore_controller($backupid, $courseid, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userid, $target);
        $target == backup::TARGET_NEW_COURSE ? : $rc->get_plan()->get_setting('overwrite_conf')->set_value(true);
        $this->assertTrue($rc->execute_precheck());

        foreach ($rc->get_plan()->get_settings() as $setting) {
            if ($setting instanceof \restore_block_opencast_setting) {
                $setting->set_value($includevideos);
            }
        }
        $rc->execute_plan();

        $course = $DB->get_record('course', array('id' => $rc->get_courseid()));

        $rc->destroy();
        unset($rc);
        return $course;
    }

    /**
     *  Execute an adhoc task like via cron function.
     */
    private function execute_adhoc_task($taskrecord) {

        $task = new \block_opencast\task\process_duplicate_event();
        $task->set_id($taskrecord->id);
        $task->set_custom_data_as_string($taskrecord->customdata);

        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
        $lock = $cronlockfactory->get_lock('adhoc_' . $taskrecord->id, 0);
        $lock->release();

        $task->set_lock($lock);

        $this->preventResetByRollback();
        ob_start();
        cron_run_inner_adhoc_task($task);
        return ob_get_clean();
    }

    private function check_task_fail_with_error($expectederrortextkey, $expectedfailedcount) {
        global $DB;

        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $taskrecord = array_shift($taskrecords);
        $a = json_decode($taskrecord->customdata);
        $course = $DB->get_record('course', array('id' => $a->courseid));
        $a->coursefullname = $course->fullname;
        $a->taskid = $taskrecord->id;
        $a->duplicateworkflow = block_opencast_apibridge_testable::DUPLICATE_WORKFLOW;

        $output = $this->execute_adhoc_task($taskrecord);
        $this->assertContains(get_string($expectederrortextkey, 'block_opencast', $a), $output);

        // Task is not deleted and countfailed sould be increased.
        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(1, count($taskrecords));

        $taskrecord = array_shift($taskrecords);
        $customdata = json_decode($taskrecord->customdata);
        $this->assertEquals($expectedfailedcount, $customdata->countfailed);
    }

    public function test_adhoctask_execution() {
        global $USER, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('apiurl', $this->apiurl, 'tool_opencast');
        set_config('keeptempdirectoriesonbackup', true);

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();

        // Create course with block opencast.
        $course = $generator->create_course();
        $coursecontext = context_course::instance($course->id);
        $generator->create_block('opencast', ['parentcontextid' => $coursecontext->id]);

        // Setup simulation data for api.
        $apibridge = \block_opencast\local\apibridge::get_instance();
        $apibridge->set_testdata('get_course_videos', $course->id, 'file');

        // Backup with videos.
        $backupid = $this->backup_course($course->id, true, $USER->id);

        // Prepare server simulation (viea apibridge).
        $apibridge->set_testdata('supports_api_level', 'level', 'v1.1.0');
        $apibridge->set_testdata('create_course_series', 'newcourse', '1234-1234-1234');

        $newcourse = $this->restore_course($backupid, 0, true, $USER->id);
        $this->assertNotEmpty($newcourse);

        // Check generated tasks.
        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(1, count($taskrecords));

        // Workflow Task not properly created, so task should fail.
        $this->check_task_fail_with_error('error_workflow_setup_missing', 1);

        // Configure workflow, but delete series for course in moodle.
        set_config('duplicateworkflow', $apibridge::DUPLICATE_WORKFLOW, 'block_opencast');
        $mapping = seriesmapping::get_record(array('courseid' => $newcourse->id));
        $mapping->delete();
        // Series missing so task should fail.
        $this->check_task_fail_with_error('error_seriesid_missing_course', 2);

        // Create wrong series for course.
        $mappingwrong = new seriesmapping();
        $mappingwrong->set('courseid', $newcourse->id);
        $mappingwrong->set('series', 'wrong-series-id');
        $mappingwrong->create();
        $this->check_task_fail_with_error('error_seriesid_not_matching', 3);

        // Create right series for course.
        $mappingwrong->delete();
        $mapping->create();
        $this->check_task_fail_with_error('error_seriesid_missing_opencast', 4);

        // Setup series in opencast simulation.
        $apibridge->set_testdata('get_course_series', $newcourse->id, '1234-1234-1234');
        $this->check_task_fail_with_error('error_workflow_not_exists', 5);

        // Setup workflow in opencast system.
        $apibridge->set_testdata('check_if_workflow_exists', $apibridge::DUPLICATE_WORKFLOW, true);
        $this->check_task_fail_with_error('error_workflow_not_started', 6);

        // Setup succesful start workflow in opencast system.
        $apibridge->set_testdata('start_workflow', $apibridge::DUPLICATE_WORKFLOW, true);

        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $taskrecord = array_shift($taskrecords);
        $output = $this->execute_adhoc_task($taskrecord);

        // Task is deleted.
        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(0, count($taskrecords));

        // Test execution fails for 10 times.
        $sink = $this->redirectMessages();

        $newcourse2 = $this->restore_course($backupid, 0, true, $USER->id);
        $this->assertNotEmpty($newcourse2);

         // Check generated tasks.
        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(1, count($taskrecords));

        $taskrecord = array_shift($taskrecords);
        $customdata = json_decode($taskrecord->customdata);
        $customdata->countfailed = 9;

        $DB->set_field('task_adhoc', 'customdata', json_encode($customdata), ['id' => $taskrecord->id]);
        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $taskrecord = array_shift($taskrecords);
        $output = $this->execute_adhoc_task($taskrecord);

        $messages = $sink->get_messages();
        $message = array_shift($messages);
        $this->assertEquals(get_string('erroremailsubj', 'block_opencast'), $message->subject);

        $taskrecords = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(0, count($taskrecords));
    }

    /**
     * Test restore of event identifiers.
     */
    public function notest_restore() {
        global $USER, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('apiurl', $this->apiurl, 'tool_opencast');
        set_config('keeptempdirectoriesonbackup', true);

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();

        // Create course with block opencast.
        $course = $generator->create_course();
        $coursecontext = context_course::instance($course->id);
        $generator->create_block('opencast', ['parentcontextid' => $coursecontext->id]);

        // Setup simulation data for api.
        $apibridge = \block_opencast\local\apibridge::get_instance();
        $apibridge->set_testdata('get_course_videos', $course->id, 'file');

        // Backup with videos.
        $backupid = $this->backup_course($course->id, true, $USER->id);

        // Try to restore into a new course must fail: api not supported.
        $newcourse = $this->restore_course($backupid, 0, true, $USER->id);
        $this->assertNotEmpty($newcourse);

        // Check generated tasks, should be none.
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(0, count($tasks));

        // Set supported api, but restore should fail because course series could not be created.
        $apibridge->set_testdata('supports_api_level', 'level', 'v1.1.0');

        ob_start();
        $sink = $this->redirectMessages();
        $newcourse = $this->restore_course($backupid, 0, true, $USER->id);
        $this->assertNotEmpty($newcourse);

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(0, count($tasks));

        $errormessage = ob_get_clean();
        $this->assertEquals(get_string('seriesnotcreated', 'block_opencast'), $errormessage);

        $messages = $sink->get_messages();
        $message = array_shift($messages);

        $this->assertEquals(get_string('errorrestoremissingseries_subj', 'block_opencast'), $message->subject);

        // Enable series creation, but delete existing events so restore should fail
        // events are not found on opencast server.
        $apibridge->set_testdata('create_course_series', 'newcourse', '1234-1234-1234');
        $apibridge->unset_testdata('get_course_videos', $course->id);

        $sink->clear();
        $newcourse = $this->restore_course($backupid, 0, true, $USER->id);
        $this->assertNotEmpty($newcourse);

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(0, count($tasks));

        $messages = $sink->get_messages();
        $message = array_shift($messages);

        $this->assertEquals(get_string('errorrestoremissingevents_subj', 'block_opencast'), $message->subject);

        // Create events on opencast server, so they can be found during restore.
        $apibridge->set_testdata('get_course_videos', $course->id, 'file');

        $newcourse = $this->restore_course($backupid, 0, true, $USER->id);
        $this->assertNotEmpty($newcourse);

        // Check generated tasks.
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\block_opencast\\task\\process_duplicate_event']);
        $this->assertEquals(1, count($tasks));
    }

    /**
     * Test backup of event identifiers.
     */
    public function notest_backup() {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('apiurl', $this->apiurl, 'tool_opencast');

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $coursecontext = context_course::instance($course->id);
        $opencastblock = $generator->create_block('opencast', ['parentcontextid' => $coursecontext->id]);

        // Check, whether the testable apibridge work as expected.
        $apibridge = \block_opencast\local\apibridge::get_instance();
        $coursevideos = $apibridge->get_course_videos_for_backup($course->id);
        $this->assertEmpty($coursevideos);

        // Backup without videos.
        $this->backup_course($course->id, false, $USER->id);
        $this->assertFalse(file_exists($this->get_backup_filename($course->id, $opencastblock->id)));

        // Backup without videos.
        $backupid = $this->backup_course($course->id, true, $USER->id);
        $this->assertFalse(file_exists($this->get_backup_filename($course->id, $opencastblock->id)));

        // Intentionally not check, the content as fixtures may be changed in future versions of opencast.
        $apibridge->set_testdata('get_course_videos', $course->id, 'file');
        $coursevideos = $apibridge->get_course_videos_for_backup($course->id);
        $this->assertNotEmpty($coursevideos);

        // Backup without videos.
        $backupid = $this->backup_course($course->id, false, $USER->id);
        $this->assertFalse(file_exists($this->get_backup_filename($course->id, $opencastblock->id)));

        // Backup with videos.
        $backupid = $this->backup_course($course->id, true, $USER->id);

        $doc = new \DOMDocument('1.0', 'utf8');
        $doc->load($this->get_backup_filename($course->id, $opencastblock->id));

        // Check site identifier.
        $apiurlelements = $doc->getElementsByTagName('apiurl');
        $apiurlelement = $apiurlelements->item(0);
        $this->assertEquals($this->apiurl, $apiurlelement->nodeValue);

        // Check all video identifier are store in opencast.xml.
        $eventidelements = $doc->getElementsByTagName('eventid');
        foreach ($eventidelements as $item) {
            unset($coursevideos[$item->nodeValue]);
        }
        $this->assertEquals(0, count($coursevideos));
    }

}
