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
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

class block_opencast_upload_testcase extends advanced_testcase {

    private $apiurl = 'moodle-proxy.rz.tu-ilmenau.de';
    private $apiusername = 'opencast_system_account';
    private $apipassword = 'CHANGE_ME';

    /**
     * Test, whether the plugin is properly installed.
     */
    public function notest_plugin_installed() {

        $config = get_config('block_opencast');
        $this->assertNotEmpty($config);
    }

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
        set_config('apiurl', $this->apiurl, 'block_opencast');
        set_config('apiusername', $this->apiusername, 'block_opencast');
        set_config('apipassword', $this->apipassword, 'block_opencast');

        // Upload file.
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('block_opencast');

        $record = [];
        $record['courseid'] = $course->id;
        $filename = $CFG->dirroot . '/blocks/opencast/tests/file/test.mp4';
        $record['filecontent'] = file_get_contents($filename);

        $file = $plugingenerator->create_file($record);
        $this->assertInstanceOf('stored_file', $file);
        \block_opencast\local\upload_helper::save_upload_jobs($course->id, $coursecontext);

        // Check upload job.
        $jobs = $DB->get_records('block_opencast_uploadjob');
        $this->assertCount(1, $jobs);

        $uploadhelper = new \block_opencast\local\upload_helper();
        $uploadhelper->cron();
    }

}
