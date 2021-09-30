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
 * Behat steps definitions for opencast.
 *
 * @package   block_opencast
 * @category  test
 * @copyright 2021 Tamara Gunkel, University of Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use tool_opencast\seriesmapping;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps definitions related with the opencast blocke.
 *
 * @copyright 2021 Tamara Gunkel, University of Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_opencast extends behat_base
{

    /**
     * Setup test opencast API.
     * @Given /^I setup the opencast test api$/
     */
    public function i_setup_the_opencast_test_api() {
        require_once(__DIR__ . '/../../../../admin/tool/opencast/tests/helper/api_testable.php');
        set_config('api_testable_responses', '[]', 'block_opencast');

        $files = ['init_api_events.json', 'init_api_workflow_definitions.json', 'init_api_workflow_definitions_duplicate_event.json',
            'api_events_filter_seriesimport.json', 'api_events_acl_secondvideo.json', 'api_series_acl.json'];
        $api_testable = new api_testable();
        foreach ($files as $file) {
            $apicall = file_get_contents(__DIR__ . "/../fixtures/api_calls/get/" . $file);
            $apicall = json_decode($apicall);
            $api_testable->add_json_response($apicall->resource, 'get', json_encode($apicall->response));
        }
    }

    /**
     * Upload a testvideo.
     * @Given /^I upload a testvideo$/
     */
    public function i_upload_a_testvideo() {
        require_once(__DIR__ . '/../../../../admin/tool/opencast/tests/helper/api_testable.php');

        $courses = core_course_category::search_courses(array('search' => 'Course 1'));

        $mapping = new seriesmapping();
        $mapping->set('courseid', reset($courses)->id);
        $mapping->set('series', '1234-1234-1234-1234-1234');
        $mapping->set('isdefault', '1');
        $mapping->set('ocinstanceid', 1);
        $mapping->create();

        $newdata = ['api_events.json', 'api_events_acl.json', 'api_events_detailpage.json', 'api_series.json',
            'api_series_metadata.json', 'api_series_two.json', 'api_events_with_publication.json',
            'api_series_filter.json','api_events_metadata.json', 'api_events_single_event.json',
            'api_events_nolimit.json'];
        $api_testable = new api_testable();
        foreach ($newdata as $file) {
            $apicall = file_get_contents(__DIR__ . "/../fixtures/api_calls/get/" . $file);
            $apicall = json_decode($apicall);
            $api_testable->add_json_response($apicall->resource, 'get', json_encode($apicall->response));
        }

        // Add post request.
        $files = ['api_series_createseries.json', 'api_workflows_updatemetadata.json', 'api_workflows_startworkflow.json'];
        $api_testable = new api_testable();
        foreach ($files as $file) {
            $apicall = file_get_contents(__DIR__ . "/../fixtures/api_calls/post/" . $file);
            $apicall = json_decode($apicall);
            $api_testable->add_json_response($apicall->resource, 'post', json_encode($apicall->response));
        }
    }

    /**
     * Create a second series.
     * @Given /^I create a second series$/
     */
    public function i_create_a_second_series() {
        require_once(__DIR__ . '/../../../../admin/tool/opencast/tests/helper/api_testable.php');

        $courses = core_course_category::search_courses(array('search' => 'Course 1'));

        $mapping = new seriesmapping();
        $mapping->set('courseid', reset($courses)->id);
        $mapping->set('series', '1111-1111-1111-1111-1111');
        $mapping->set('isdefault', '0');
        $mapping->set('ocinstanceid', 1);
        $mapping->create();

        $newdata = ['api_series_filter_two.json'];
        $api_testable = new api_testable();
        foreach ($newdata as $file) {
            $apicall = file_get_contents(__DIR__ . "/../fixtures/api_calls/get/" . $file);
            $apicall = json_decode($apicall);
            $api_testable->add_json_response($apicall->resource, 'get', json_encode($apicall->response));
        }
    }
}