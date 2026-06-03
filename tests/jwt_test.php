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
 * Test JWT Service
 * @package    block_opencast
 * @copyright  2026 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/opencast/tests/helper/tool_opencast_test_type_helper.php');

use advanced_testcase;
use block_opencast\local\apibridge;
use tool_opencast\local\jwt_service;
use OpencastApi\Util\OcUtils;
use tool_opencast_test_type_helper;
use block_opencast_renderer;

/**
 * Test JWT Service and its functionalities.
 *
 * @group tool_opencast
 * @group tool_opencast_jwt
 * @package    tool_opencast
 * @copyright  2026 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class jwt_test extends advanced_testcase {
    /** @var string Test api url. */
    private $apiurl = 'http://localhost:8081';
    /** @var string Test api username. */
    private $apiusername = 'admin';
    /** @var string Test api password. */
    private $apipassword = 'opencast';
    /** @var int the curl timeout in milliseconds */
    private $apitimeout = 2000;
    /** @var int the curl connecttimeout in milliseconds */
    private $apiconnecttimeout = 1000;
    /** @var string the jwt private key text */
    private $jwtprivatekey = "-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIJyVBVwEP05kgIfOxLEjd7qWZPu1HYZ1lNEZrXDc0CWJoAoGCCqGSM49
AwEHoUQDQgAENN9jCcHjZ8pCxPeM+rYSDlZm0OCLvTYdldHfs0zG4pks/NASlitO
5N1sUX/zBEsYXdz11v5uGvQIZDivP30TDQ==
-----END EC PRIVATE KEY-----";

    /**
     * Overriding setUp() function to always check the test type and prepare the tests.
     */
    public function setUp(): void {
        parent::setUp();
        if (!tool_opencast_test_type_helper::is_jwt_test()) {
            $this->markTestSkipped('Skipping JWT tests because of the targeted test type does not match!');
        }
    }

    /**
     * Prepare everything for the actual real testing environment.
     * @param bool $jwtenabled the flag to enable/disable JWT service.
     * @return array the required variables for the tests.
     */
    private function notest_prepare_test(bool $jwtenabled = true): array {
        global $COURSE;
        $this->setAdminUser();
        $this->notest_apply_plugin_configs($jwtenabled);
        apibridge::set_testing(false);
        $apibridge = apibridge::get_instance(1, true);

        // Setup course with tool, groups and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $COURSE = $course;

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $params = ['limit' => 1];
        $seriesrepsonse = $apibridge->api->opencastapi->seriesApi->getAll($params);
        $this->assertEquals(200, $seriesrepsonse['code'], 'Cannot reach Opencast!');
        $seriesarray = $seriesrepsonse['body'];
        $series = reset($seriesarray);
        $this->assertNotEmpty($series, 'Series must not be empty!');

        // Handle course series mapping with ACL change!
        $result = $apibridge->import_series_to_course_with_acl_change($course->id, $series->identifier, $teacher->id);
        $this->assertEquals(0, $result->error, 'Series mapping gone wrong!');

        $videos = $apibridge->get_series_videos($series->identifier);
        $this->assertEquals(0, $videos->error, 'There must be video present in the series!');

        $video = reset($videos->videos);
        $this->assertNotEmpty($video, 'Video object must not be empty!');

        $params = ['limit' => 1];
        $playlistsresponse = $apibridge->api->opencastapi->playlistsApi->getAll($params);
        $this->assertEquals(200, $playlistsresponse['code'], 'Cannot reach Opencast!');
        $playlistsarray = $playlistsresponse['body'];
        $playlist = reset($playlistsarray);
        $this->assertNotEmpty($playlist, 'Playlist must not be empty!');

        $isvisible = $apibridge->is_event_visible($video->identifier, $course->id);
        if ($isvisible !== block_opencast_renderer::VISIBLE) {
            $republishmetadata = $apibridge->start_workflow($video->identifier, 'republish-metadata');

            $this->assertEquals(true, $republishmetadata, 'Unable to republish metadata!');

            $isprocessed = $this->notest_ensure_video_processed($video->identifier, $apibridge);
            $this->assertTrue($isprocessed, 'Video was not processed successfully after ACL Change.');
        }

        return [$apibridge, $course, $teacher, $student, $series, $video, $playlist];
    }

    /**
     * Apply relevant configuration for the plugin.
     * @param bool $jwtenabled flag to enable or disable JWT.
     */
    private function notest_apply_plugin_configs(bool $jwtenabled = true) {
         // Test api bridge.
        set_config('apiurl_1', $this->apiurl, 'tool_opencast');
        set_config('apiusername_1', $this->apiusername, 'tool_opencast');
        set_config('apipassword_1', $this->apipassword, 'tool_opencast');
        set_config('apitimeout_1', $this->apitimeout, 'tool_opencast');
        set_config('apiconnecttimeout_1', $this->apiconnecttimeout, 'tool_opencast');
        set_config('uploadworkflow_1', 'fast', 'tool_opencast'); // To make sure it runs faster.
        set_config('series_name_1', '[COURSENAME]', 'tool_opencast');
        set_config('roles_1',
            '[{"rolename":"ROLE_ADMIN","actions":"write,read","permanent":1},' .
            '{"rolename":"ROLE_GROUP_MH_DEFAULT_ORG_EXTERNAL_APPLICATIONS","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEID]_Instructor","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEGROUPID]_Learner","actions":"read","permanent":0}]',
            'tool_opencast');
        set_config('aclownerrole_1', 'ROLE_OWNER_[USER_ID]', 'tool_opencast');
        set_config('jwt_enabled_1', $jwtenabled ? 1 : 0, 'tool_opencast');
        set_config('jwt_privatekey_1', $this->jwtprivatekey, 'tool_opencast');
        set_config('jwt_algorithm_1', jwt_service::CONFIGS_DEFAULT_ALGORITHM, 'tool_opencast');
        $config = get_config('tool_opencast');
        $this->assertNotEmpty($config);
    }

    /**
     * Test main JWT features and functionalities against real server and accessibility checks.
     * With JWT activated.
     *
     * @covers \tool_opencast\local\jwt_service
     */
    public function test_jwt_main_features_activated(): void {
        $this->resetAfterTest();
        [$apibridge, $course, $teacher, $student, $series, $video, $playlist] = $this->notest_prepare_test(true);

        // Now that everything is ready, we start with asserting JWT.
        $assertingvideos = $apibridge->get_block_videos($course->id, false);
        $this->assertEquals(0, $assertingvideos->error, 'No video found! Something went wrong!');
        $assertingvideo = reset($assertingvideos->videos);
        $isjwtinjected = $this->notest_assert_jwt_injection_in_publications($assertingvideo);
        $this->assertTrue($isjwtinjected, 'Method get_block_videos has no proper JWT injection!');

        $assertingvideof = $apibridge->get_opencast_video($assertingvideo->identifier, true);
        $this->assertEquals(0, $assertingvideof->error, 'No video found! Something went wrong!');
        $assertingvideo = $assertingvideof->video;
        $isjwtinjected = $this->notest_assert_jwt_injection_in_publications($assertingvideo);
        $this->assertTrue($isjwtinjected, 'Method get_opencast_video has no proper JWT injection!');

        $assertingvideos = $apibridge->get_series_videos($series->identifier);
        $this->assertEquals(0, $assertingvideos->error, 'There must be video present for in the series!');
        $assertingvideo = reset($assertingvideos->videos);
        $isjwtinjected = $this->notest_assert_jwt_injection_in_publications($assertingvideo);
        $this->assertTrue($isjwtinjected, 'Method get_opencast_video has no proper JWT injection!');

        $jwthandler = $apibridge->api->opencastapi->getRestJwtHandler();
        // Done with apibridge crucial JWT changes.
        $studioaccesstoken = $apibridge->api->jwtservice->issue_jwt_for_ext_service_studio();
        $isvalid = $jwthandler->validateToken($studioaccesstoken);
        $this->assertTrue($isvalid, 'Studio JWT is not valid!');

        $editoraccesstoken = $apibridge->api->jwtservice->issue_jwt_for_ext_service_editor();
        $isvalid = $jwthandler->validateToken($editoraccesstoken);
        $this->assertTrue($isvalid, 'Editor JWT is not valid!');

        $annotationaccesstoken = $apibridge->api->jwtservice->issue_jwt_for_ext_service_annotation($video->identifier);
        $isvalid = $jwthandler->validateToken($annotationaccesstoken);
        $this->assertTrue($isvalid, 'Annotation JWT is not valid!');

        $eventaccesstoken = $apibridge->api->jwtservice->issue_jwt_for_event($video->identifier);
        $isvalid = $jwthandler->validateToken($eventaccesstoken);
        $this->assertTrue($isvalid, 'Event Single Issued JWT is not valid!');

        // For Teacher.
        $attachments = OcUtils::findValueByKey($video->publications, 'attachments');
        $eventurl = OcUtils::findValueByKey($attachments, 'url');
        $parsedurl = (array) parse_url($eventurl);
        if (isset($parsedurl['query'])) {
            unset($parsedurl['query']);
        }
        $filteredurl = $apibridge->api->jwtservice->unparse_url($parsedurl);
        $eventurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_event($filteredurl, $video->identifier);
        $eventurlwithjwt = str_replace('8080', '8081', $eventurlwithjwt);
        $this->notest_assert_url_accessibility($eventurlwithjwt);

        $seriesurl = $this->apiurl . '/api/series/' . $series->identifier;
        $seriesurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_series($seriesurl, $series->identifier);
        $this->notest_assert_url_accessibility($seriesurlwithjwt);

        $playlisturl = $this->apiurl . '/api/playlist/' . $playlist->id;
        $playlisturlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_playlist($playlisturl, $playlist->id);
        $this->notest_assert_url_accessibility($playlisturlwithjwt);

        // For Student.
        $this->setUser($student);
        $eventurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_event($filteredurl, $video->identifier);
        $eventurlwithjwt = str_replace('8080', '8081', $eventurlwithjwt);
        $this->notest_assert_url_accessibility($eventurlwithjwt);

        $seriesurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_series($seriesurl, $series->identifier);
        $this->notest_assert_url_accessibility($seriesurlwithjwt);

        $playlisturlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_playlist($playlisturl, $playlist->id);
        $this->notest_assert_url_accessibility($playlisturlwithjwt);

        // Return the user back to Teacher.
        $this->setUser($teacher);

        $iframehtml = $apibridge->api->jwtservice->get_jwt_iframe_player_html(
            1,
            $video->identifier,
            [],
            $this->apiurl
        );
        $this->assertNotEmpty($iframehtml, 'Unable to generate iframe html!');

        $studiourlpath = $apibridge->generate_studio_url_path($course->id, $series->identifier);
        $jwt = $apibridge->api->jwtservice->issue_jwt_for_ext_service_studio();
        $targeturl = rtrim($this->apiurl, '/') . $studiourlpath;
        $redirecthtml = $apibridge->api->jwtservice->get_jwt_redirect_form($jwt, $targeturl);
        $this->assertNotEmpty($redirecthtml, 'Unable to generate redirect form html!');

        // For student.
        $this->setUser($student);
        $studiourlpath = $apibridge->generate_studio_url_path($course->id, $series->identifier);
        $jwt = $apibridge->api->jwtservice->issue_jwt_for_ext_service_studio();
        $targeturl = rtrim($this->apiurl, '/') . $studiourlpath;
        $redirecthtml = $apibridge->api->jwtservice->get_jwt_redirect_form($jwt, $targeturl);
        $this->assertNotEmpty($redirecthtml, 'Unable to generate redirect form html!');
    }

    /**
     * Test main JWT features and functionalities against real server and accessibility checks.
     * With JWT deactivated!
     *
     * @covers \tool_opencast\local\jwt_service
     */
    public function test_jwt_main_features_deactivated(): void {
        $this->resetAfterTest();
        [$apibridge, $course, $teacher, $student, $series, $video, $playlist] = $this->notest_prepare_test(false);

        $assertingvideos = $apibridge->get_block_videos($course->id, false);
        $this->assertEquals(0, $assertingvideos->error, 'No video found! Something went wrong!');
        $assertingvideo = reset($assertingvideos->videos);
        $isjwtinjected = $this->notest_assert_jwt_injection_in_publications($assertingvideo);
        $this->assertFalse($isjwtinjected, 'There should be no JWT Present in the publication');

        $assertingvideof = $apibridge->get_opencast_video($assertingvideo->identifier, true);
        $this->assertEquals(0, $assertingvideof->error, 'No video found! Something went wrong!');
        $assertingvideo = $assertingvideof->video;
        $isjwtinjected = $this->notest_assert_jwt_injection_in_publications($assertingvideo);
        $this->assertFalse($isjwtinjected, 'Method get_opencast_video still returns injects JWT but it should not!');

        $assertingvideos = $apibridge->get_series_videos($series->identifier);
        $this->assertEquals(0, $assertingvideos->error, 'There must be video present for in the series!');
        $assertingvideo = reset($assertingvideos->videos);
        $isjwtinjected = $this->notest_assert_jwt_injection_in_publications($assertingvideo);
        $this->assertFalse($isjwtinjected, 'Method get_opencast_video still returns injects JWT but it should not!');

        $studioaccesstoken = $apibridge->api->jwtservice->issue_jwt_for_ext_service_studio();
        $this->assertEmpty($studioaccesstoken, 'JWT Access token must be empty for Studio!');

        $editoraccesstoken = $apibridge->api->jwtservice->issue_jwt_for_ext_service_editor();
        $this->assertEmpty($editoraccesstoken, 'JWT Access token must be empty for Editor!');

        $annotationaccesstoken = $apibridge->api->jwtservice->issue_jwt_for_ext_service_annotation($video->identifier);
        $this->assertEmpty($annotationaccesstoken, 'JWT Access token must be empty for Annotation!');

        $eventaccesstoken = $apibridge->api->jwtservice->issue_jwt_for_event($video->identifier);
        $this->assertEmpty($eventaccesstoken, 'JWT Access token must be empty for issuing single event token!');

        // For Teacher.
        $attachments = OcUtils::findValueByKey($video->publications, 'attachments');
        $eventurl = OcUtils::findValueByKey($attachments, 'url');
        $parsedurl = (array) parse_url($eventurl);
        if (isset($parsedurl['query'])) {
            unset($parsedurl['query']);
        }
        $filteredurl = $apibridge->api->jwtservice->unparse_url($parsedurl);
        $eventurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_event($filteredurl, $video->identifier);
        $eventurlwithjwt = str_replace('8080', '8081', $eventurlwithjwt);
        $this->notest_assert_url_accessibility($eventurlwithjwt, false);

        $seriesurl = $this->apiurl . '/api/series/' . $series->identifier;
        $seriesurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_series($seriesurl, $series->identifier);
        $this->notest_assert_url_accessibility($seriesurlwithjwt, false);

        $playlisturl = $this->apiurl . '/api/playlist/' . $playlist->id;
        $playlisturlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_playlist($playlisturl, $playlist->id);
        $this->notest_assert_url_accessibility($playlisturlwithjwt, false);

        // For Student.
        $this->setUser($student);
        $eventurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_event($filteredurl, $video->identifier);
        $eventurlwithjwt = str_replace('8080', '8081', $eventurlwithjwt);
        $this->notest_assert_url_accessibility($eventurlwithjwt, false);

        $seriesurlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_series($seriesurl, $series->identifier);
        $this->notest_assert_url_accessibility($seriesurlwithjwt, false);

        $playlisturlwithjwt = $apibridge->api->jwtservice->attach_jwt_url_param_playlist($playlisturl, $playlist->id);
        $this->notest_assert_url_accessibility($playlisturlwithjwt, false);

        // Return the user back to Teacher.
        $this->setUser($teacher);

        $iframehtml = $apibridge->api->jwtservice->get_jwt_iframe_player_html(
            1,
            $video->identifier,
            [],
            $this->apiurl
        );
        $this->assertFalse($iframehtml, 'Iframe html must be false!');

        $studiourlpath = $apibridge->generate_studio_url_path($course->id, $series->identifier);
        $jwt = '';
        $targeturl = rtrim($this->apiurl, '/') . $studiourlpath;
        $redirecthtml = $apibridge->api->jwtservice->get_jwt_redirect_form($jwt, $targeturl);
        $this->assertFalse($redirecthtml, 'Redirect form must be false!');
    }

    /**
     * Assert the accessibility of a url.
     * @param string $url The url to check accessibility for.
     * @param bool $isaccessible The flag to check if the url is accessible or not.
     * @return void
     */
    private function notest_assert_url_accessibility(string $url, bool $isaccessible = true): void {
        $headers = @get_headers($url);
        $this->assertNotFalse($headers, "The server is unreachable.");
        $statusline = $headers[0];
        preg_match('/HTTP\/\d+\.\d+\s+(\d+)/i', $statusline, $matches);
        $statuscode = isset($matches[1]) ? (int)$matches[1] : 0;

        $targetstatuscodes = [401, 403, 404];
        if ($isaccessible) {
            $this->assertNotContains($statuscode, $targetstatuscodes, 'URL is not accessible!' . $statuscode);
        } else {
            $this->assertContains($statuscode, $targetstatuscodes, 'URL should not be accessible but it is!' . $statuscode);
        }
    }

    /**
     * Helps asserting jwt oinjection in publication urls and uris.
     * @param object $assertingvideo the video to check
     * @return bool whether the jwt is injected or not.
     */
    private function notest_assert_jwt_injection_in_publications(object $assertingvideo): bool {
        $publications = OcUtils::findValueByKey($assertingvideo, 'publications');
        $firsturl = OcUtils::findValueByKey($publications, 'url');
        $firsturi = OcUtils::findValueByKey($publications, 'uri');
        if (empty($firsturl) && empty($firsturi)) {
            return false;
        }
        if (!empty($firsturl) && !str_contains($firsturl, 'jwt=')) {
            return false;
        }
        if (!empty($firsturi) && !str_contains($firsturi, 'jwt=')) {
            return false;
        }
        return true;
    }

    /**
     * Checks, if the video has been processed successfully after upload.
     *
     * @param string $identifier The Opencast video identifier.
     * @param apibridge $apibridge the apibridge instance
     *
     * @return bool true if the video is available, false otherwise.
     */
    private function notest_check_processed_video($identifier, $apibridge) {
        $video = $apibridge->get_opencast_video($identifier, true);
        if ($video->error) {
            return false;
        }
        if ($video->video->processing_state != 'SUCCEEDED') {
            return false;
        }
        return true;
    }

    /**
     * Waits for a video to be processed successfully after upload by polling its processing state.
     *
     * This helper method repeatedly checks the processing state of a video using its identifier
     * and the provided apibridge instance.
     * It waits up to a fixed number of attempts, sleeping between checks,
     * and returns true if the video reaches the 'SUCCEEDED' state.
     *
     * @param string $identifier The Opencast video identifier.
     * @param apibridge $apibridge The apibridge instance to use for API calls.
     * @return bool True if the video is processed successfully within the limit, false otherwise.
     */
    private function notest_ensure_video_processed($identifier, $apibridge) {
        $isprocessed = false;
        $limiter = 40;
        $counter = 0;
        do {
            $isprocessed = $this->notest_check_processed_video($identifier, $apibridge);
            $counter++;
            if (!$isprocessed) {
                sleep(15);
            }
            if ($counter >= $limiter) {
                break;
            }
        } while (!$isprocessed);

        return $isprocessed;
    }
}
