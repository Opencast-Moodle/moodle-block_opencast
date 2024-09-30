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
 * Test Upload video with ingest and user defined configuration panel.
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use advanced_testcase;
use block_opencast\local\apibridge;
use block_opencast\local\upload_helper;
use block_opencast\local\workflowconfiguration_helper;
use coding_exception;
use context_course;
use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Test Upload video with ingest and user defined configuration panel.
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upload_ingest_with_configpanel_test extends advanced_testcase {


    /** @var string Test api url. */
    private $apiurl = 'https://stable.opencast.org';
     /** @var string Test api version. */
    private $apiversion = 'v1.11.0';
    /** @var string Test api username. */
    private $apiusername = 'admin';
    /** @var string Test api password. */
    private $apipassword = 'opencast';
    /** @var int the curl timeout in milliseconds */
    private $apitimeout = 2000;
    /** @var int the curl connecttimeout in milliseconds */
    private $apiconnecttimeout = 1000;

    /**
     * Uploads a file to the opencast server using ingest with user defined configration panel,
     * then checks if it was transmitted and the workflow configuration has been receieved by opencast correctly.
     *
     * @covers \block_opencast\local\upload_helper \block_opencast\local\workflowconfiguration_helper
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_upload_ingest_configpanel(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $coursecontext = context_course::instance($course->id);

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Tool settings.
        set_config('apiurl_1', $this->apiurl, 'tool_opencast');
        set_config('apiversion_1', $this->apiversion, 'tool_opencast');
        set_config('apiusername_1', $this->apiusername, 'tool_opencast');
        set_config('apipassword_1', $this->apipassword, 'tool_opencast');
        set_config('apitimeout_1', $this->apitimeout, 'tool_opencast');
        set_config('apiconnecttimeout_1', $this->apiconnecttimeout, 'tool_opencast');
        // Block settings.
        set_config('ingestupload_1', 1, 'block_opencast');
        set_config('uploadworkflow_1', 'schedule-and-upload', 'block_opencast');
        set_config('enableuploadwfconfigpanel_1', 1, 'block_opencast');
        set_config('alloweduploadwfconfigs_1', 'straightToPublishing', 'block_opencast');
        set_config('limituploadjobs_1', 2, 'block_opencast');
        set_config('series_name_1', '[COURSENAME]', 'block_opencast');
        set_config('roles_1',
            '[{"rolename":"ROLE_ADMIN","actions":"write,read","permanent":1},' .
            '{"rolename":"ROLE_GROUP_MH_DEFAULT_ORG_EXTERNAL_APPLICATIONS","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEID]_Instructor","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEGROUPID]_Learner","actions":"read","permanent":0}]',
            'block_opencast');

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
            'value' => 'test',
        ];
        $metadata[] = $obj;
        $options = new stdClass();
        $options->metadata = json_encode($metadata);
        $options->presenter = $file ? $file->get_itemid() : '';
        $options->presentation = $file ? $file->get_itemid() : '';

        // Workflow configuration helper assertions.
        $wfconfighelper = workflowconfiguration_helper::get_instance(1);
        $this->assertTrue($wfconfighelper->can_provide_configuration_panel());
        $this->assertNotEmpty($wfconfighelper->get_upload_workflow_configuration_panel());
        $this->assertNotEmpty($wfconfighelper->get_allowed_upload_configurations());

        $configpanelelementmapping = ['straightToPublishing' => 'boolean'];
        $formdata = new stdClass();
        $formdata->{$wfconfighelper::MAPPING_INPUT_HIDDEN_ID} = json_encode($configpanelelementmapping);

        // Having the straightToPublishing as false, in order to bypass the default value and check it later.
        $formdata->straightToPublishing = 0;

        $configpaneldata = $wfconfighelper->get_userdefined_configuration_data($formdata);
        $this->assertArrayHasKey('straightToPublishing', $configpaneldata);

        $workflowconfiguration = json_encode($configpaneldata);

        upload_helper::save_upload_jobs(1, $course->id, $options, null, $workflowconfiguration);

        // Check upload job.
        $jobs = $DB->get_records('block_opencast_uploadjob');
        $this->assertCount(1, $jobs);

        apibridge::set_testing(false);
        $apibridge = apibridge::get_instance(1, true);

        $uploadhelper = new upload_helper();
        $isuploaded = false;
        $limiter = 5;
        $counter = 0;
        do {
            $isuploaded = $this->notest_check_uploaded_video($course->id, $apibridge);
            $counter++;
            if ($counter >= $limiter) {
                break;
            }
        } while (!$isuploaded);

        // Check if video was uploaded.
        $videos = $apibridge->get_course_videos($course->id);

        $this->assertEmpty($videos->error, 'There was an error: ' . $videos->error);
        $this->assertCount(1, $videos->videos);

        // Now we look for the workflowid in the uploadjob table.
        $opencasteventid = $videos->videos[0]->identifier;
        $uploadjob = $DB->get_record('block_opencast_uploadjob', ['opencasteventid' => $opencasteventid]);
        $this->assertNotEmpty($uploadjob->workflowid);

        $workflowinstanceid = $uploadjob->workflowid;
        $workflowinstance = $apibridge->get_workflow_instance($workflowinstanceid);
        $this->assertNotEmpty($workflowinstance);
        $this->assertEquals("false", $workflowinstance->configuration->straightToPublishing);
    }

    /**
     * Checks, if the video is available after upload, by running cron first to make sure upload video took place successfully.
     *
     * @param int $courseid Course ID
     * @param apibridge $apibridge the apibridge instance
     *
     * @return bool true if the video is avialable, false otherwise.
     */
    private function notest_check_uploaded_video($courseid, $apibridge) {
        $uploadhelper = new upload_helper();
        // Prevent mtrace output, which would be considered risky.
        ob_start();
        $uploadhelper->cron();
        ob_end_clean();
        sleep(15);
        $videos = $apibridge->get_course_videos($courseid);
        return (!empty($videos->videos)) ? true : false;
    }
}
