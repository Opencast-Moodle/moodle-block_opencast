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
 * Unit tests for the block_opencast implementation of the video upload.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_opencast;
use advanced_testcase;
use context_course;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit tests for the block_opencast implementation of the video upload.
 *
 * @group block_opencast
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_test extends advanced_testcase {

    /** @var string Test api url. */
    private $apiurl = 'http://localhost:8080';
    /** @var string Test api username. */
    private $apiusername = 'admin';
    /** @var string Test api password. */
    private $apipassword = 'opencast';

    /**
     * Test, whether the plugin is properly installed.
     */
    public function notest_plugin_installed() {

        $config = get_config('block_opencast');
        $this->assertNotEmpty($config);
    }

    /**
     * Uploads a file to the opencast server and checks if it was transmitted.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_upload() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $coursecontext = context_course::instance($course->id);

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Test api bridge.
        set_config('apiurl', $this->apiurl, 'tool_opencast');
        set_config('apiusername', $this->apiusername, 'tool_opencast');
        set_config('apipassword', $this->apipassword, 'tool_opencast');
        set_config('limituploadjobs_1', 2, 'block_opencast');
        set_config('series_name_1', '[COURSENAME]', 'block_opencast');

        // Upload file.
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('block_opencast');

        $record = [];
        $record['courseid'] = $course->id;
        $filename = $CFG->dirroot . '/blocks/opencast/tests/fixtures/test.mp4';
        $record['filecontent'] = file_get_contents($filename);

        $file = $plugingenerator->create_file($record);
        $this->assertInstanceOf('stored_file', $file);
        $obj = [
            'id' => 'title',
            'value' => 'test'
        ];
        $metadata[] = $obj;
        $options = new \stdClass();
        $options->metadata = json_encode($metadata);
        $options->presenter = $file ? $file->get_itemid() : '';
        $options->presentation = $file ? $file->get_itemid() : '';
        \block_opencast\local\upload_helper::save_upload_jobs(1, $course->id, $options);

        // Check upload job.
        $jobs = $DB->get_records('block_opencast_uploadjob');
        $this->assertCount(1, $jobs);

        \block_opencast\local\apibridge::set_testing(false);
        $api = \block_opencast\local\apibridge::get_instance(1, true);

        $uploadhelper = new \block_opencast\local\upload_helper();
        // Prevent mtrace output, which would be considered risky.
        ob_start();
        // Upload the file.
        $uploadhelper->cron();
        sleep(20);
        $uploadhelper->cron();
        sleep(20);
        $uploadhelper->cron();
        ob_end_clean();

        // Check if video was uploaded.
        $videos = $api->get_course_videos($course->id);

        $this->assertEmpty($videos->error, 'There was an error: ' . $videos->error);
        $this->assertCount(1, $videos->videos);
    }

}
