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
 * Unit tests for the block_opencast implementation of the privacy API.
 *
 * @package    block_opencast
 * @category   test
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\approved_contextlist;
use \block_opencast\privacy\provider;

/**
 * Unit tests for the block_opencast implementation of the privacy API.
 *
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_privacy_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Overriding setUp() function to always reset after tests.
     */
    public function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new collection('block_opencast');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(2, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('block_opencast_uploadjob', $table->get_name());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('fileid', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('status', $privacyfields);
        $this->assertArrayHasKey('courseid', $privacyfields);
        $this->assertArrayHasKey('timecreated', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);

        $this->assertEquals('privacy:metadata:block_opencast_uploadjob', $table->get_summary());

        $table = next($itemcollection);
        $this->assertEquals('opencast', $table->get_name());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('file', $privacyfields);

        $this->assertEquals('privacy:metadata:opencast', $table->get_summary());
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {
        global $DB;

        // Test setup.
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        // Add two upload jobs for the User.
        $job = new \stdClass();
        $job->fileid = 1;
        $job->contenthash = '1234567';
        $job->opencasteventid = '';
        $job->countfailed = 0;
        $job->timestarted = 0;
        $job->timesucceeded = 0;
        $job->status = \block_opencast\local\upload_helper::STATUS_READY_TO_UPLOAD;
        $job->courseid = 1;
        $job->userid = $teacher->id;
        $job->timecreated = time();
        $job->timemodified = time();

        $DB->insert_record('block_opencast_uploadjob', $job);

        // Add two upload jobs for the User.
        $job = new \stdClass();
        $job->fileid = 3;
        $job->contenthash = '987654321';
        $job->opencasteventid = '';
        $job->countfailed = 0;
        $job->timestarted = 0;
        $job->timesucceeded = 0;
        $job->status = \block_opencast\local\upload_helper::STATUS_CREATING_EVENT;
        $job->courseid = 1;
        $job->userid = $teacher->id;
        $job->timecreated = time();
        $job->timemodified = time();

        $DB->insert_record('block_opencast_uploadjob', $job);

        // Test the User's retrieved contextlist contains only one context.
        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);

        // Test the User's contexts equal the User's own context.
        $context = reset($contexts);
        $this->assertEquals(CONTEXT_USER, $context->contextlevel);
        $this->assertEquals($teacher->id, $context->instanceid);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_user_data() {
        global $DB;

        // Test setup.
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        // Add 3 upload jobs for the User.
        for ($c = 0; $c < 3; $c++) {
            $job = new \stdClass();
            $job->fileid = $c;
            $job->contenthash = '987654321' . $c;
            $job->opencasteventid = '';
            $job->countfailed = 0;
            $job->timestarted = 0;
            $job->timesucceeded = 0;
            $job->status = \block_opencast\local\upload_helper::STATUS_CREATING_EVENT;
            $job->courseid = 1;
            $job->userid = $teacher->id;
            $job->timecreated = time();
            $job->timemodified = time();

            $DB->insert_record('block_opencast_uploadjob', $job);
        }

        // Test the created block_opencast records matches the test number of jobs specified.
        $jobs = $DB->get_records('block_opencast_uploadjob', ['userid' => $teacher->id]);
        $this->assertCount(3, $jobs);

        // Test the User's retrieved contextlist contains only one context.
        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);

        // Test the User's contexts equal the User's own context.
        $context = reset($contexts);
        $this->assertEquals(CONTEXT_USER, $context->contextlevel);
        $this->assertEquals($teacher->id, $context->instanceid);

        $approvedcontextlist = new approved_contextlist($teacher, 'block_opencast', $contextlist->get_contextids());

        // Retrieve Calendar Event and Subscriptions data only for this user.
        provider::export_user_data($approvedcontextlist);

        // Test the block_opencast data is exported at the User context level.
        $user = $approvedcontextlist->get_user();
        $contextuser = context_user::instance($user->id);
        $writer = writer::with_context($contextuser);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        // Test setup.
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        // Add an upload job for the User.
        $job = new \stdClass();
        $job->fileid = 3;
        $job->contenthash = '987654321';
        $job->opencasteventid = '';
        $job->countfailed = 0;
        $job->timestarted = 0;
        $job->timesucceeded = 0;
        $job->status = \block_opencast\local\upload_helper::STATUS_CREATING_EVENT;
        $job->courseid = 1;
        $job->userid = $teacher->id;
        $job->timecreated = time();
        $job->timemodified = time();

        $DB->insert_record('block_opencast_uploadjob', $job);

        // Test the User's retrieved contextlist contains only one context.
        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);

        // Test the User's contexts equal the User's own context.
        $context = reset($contexts);
        $this->assertEquals(CONTEXT_USER, $context->contextlevel);
        $this->assertEquals($teacher->id, $context->instanceid);

        // Test delete all users content by context.
        provider::delete_data_for_all_users_in_context($context);
        $blockopencast = $DB->get_records('block_opencast_uploadjob', ['userid' => $teacher->id]);
        $this->assertCount(0, $blockopencast);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        // Test setup.
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $this->setUser($teacher1);

        // Add 3 upload jobs for Teacher 1.
        for ($c = 0; $c < 3; $c++) {
            $job = new \stdClass();
            $job->fileid = $c;
            $job->contenthash = '987654321' . $c;
            $job->opencasteventid = '';
            $job->countfailed = 0;
            $job->timestarted = 0;
            $job->timesucceeded = 0;
            $job->status = \block_opencast\local\upload_helper::STATUS_CREATING_EVENT;
            $job->courseid = 1;
            $job->userid = $teacher1->id;
            $job->timecreated = time();
            $job->timemodified = time();

            $DB->insert_record('block_opencast_uploadjob', $job);
        }

        // Add 1 upload jobs for Teacher 2.
        $job = new \stdClass();
        $job->fileid = 10;
        $job->contenthash = '987654321';
        $job->opencasteventid = '';
        $job->countfailed = 0;
        $job->timestarted = 0;
        $job->timesucceeded = 0;
        $job->status = \block_opencast\local\upload_helper::STATUS_CREATING_EVENT;
        $job->courseid = 1;
        $job->userid = $teacher2->id;
        $job->timecreated = time();
        $job->timemodified = time();

        $DB->insert_record('block_opencast_uploadjob', $job);

        // Test the created block_opencast records for Teacher 1 equals test number of jobs specified.
        $jobs = $DB->get_records('block_opencast_uploadjob', ['userid' => $teacher1->id]);
        $this->assertCount(3, $jobs);

        // Test the created block_opencast_uploadjob records for Teacher 2 equals 1.
        $jobs = $DB->get_records('block_opencast_uploadjob', ['userid' => $teacher2->id]);
        $this->assertCount(1, $jobs);

        // Test the deletion of block_opencast_uploadjob records for Teacher 1 results in zero records.
        $contextlist = provider::get_contexts_for_userid($teacher1->id);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);

        // Test the User's contexts equal the User's own context.
        $context = reset($contexts);
        $this->assertEquals(CONTEXT_USER, $context->contextlevel);
        $this->assertEquals($teacher1->id, $context->instanceid);

        $approvedcontextlist = new approved_contextlist($teacher1, 'block_opencast', $contextlist->get_contextids());
        provider::delete_data_for_user($approvedcontextlist);
        $jobs = $DB->get_records('block_opencast_uploadjob', ['userid' => $teacher1->id]);
        $this->assertCount(0, $jobs);

        // Test that Teacher 2's single block_opencast_uploadjob record still exists.
        $contextlist = provider::get_contexts_for_userid($teacher2->id);
        $contexts = $contextlist->get_contexts();
        $this->assertCount(1, $contexts);

        // Test the User's contexts equal the User's own context.
        $context = reset($contexts);
        $this->assertEquals(CONTEXT_USER, $context->contextlevel);
        $this->assertEquals($teacher2->id, $context->instanceid);

        $jobs = $DB->get_records('block_opencast_uploadjob', ['userid' => $teacher2->id]);
        $this->assertCount(1, $jobs);
    }

}
