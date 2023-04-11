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

use tool_opencast\local\api_testable;
use tool_opencast\seriesmapping;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps definitions related with the opencast blocke.
 *
 * @copyright 2021 Tamara Gunkel, University of Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_opencast extends behat_base {

    /**
     * Setup test opencast API.
     * @Given /^I setup the opencast test api$/
     */
    public function i_setup_the_opencast_test_api() {
        set_config('api_testable_responses', '[]', 'block_opencast');
        $apitestable = $this->get_apitestable_with_loaded_calls();
    }

    /**
     * Upload a testvideo.
     * @Given /^I upload a testvideo$/
     */
    public function i_upload_a_testvideo() {
        $courses = core_course_category::search_courses(array('search' => 'Course 1'));

        $mapping = new seriesmapping();
        $mapping->set('courseid', reset($courses)->id);
        $mapping->set('series', '1234-1234-1234-1234-1234');
        $mapping->set('isdefault', '1');
        $mapping->set('ocinstanceid', 1);
        $mapping->create();
        $apitestable = $this->get_apitestable_with_loaded_calls();
    }

    /**
     * Create a second series.
     * @Given /^I create a second series$/
     */
    public function i_create_a_second_series() {
        $courses = core_course_category::search_courses(array('search' => 'Course 1'));

        $mapping = new seriesmapping();
        $mapping->set('courseid', reset($courses)->id);
        $mapping->set('series', '1111-1111-1111-1111-1111');
        $mapping->set('isdefault', '0');
        $mapping->set('ocinstanceid', 1);
        $mapping->create();
        $apitestable = $this->get_apitestable_with_loaded_calls();
    }

    /**
     * Gets an apitestable instance after loading all mock responses.
     * @return \tool_opencast\local\api_testable apitestable instance
     */
    protected function get_apitestable_with_loaded_calls() {
        $apitestable = new api_testable();
        if (empty($apitestable->get_json_responses())) {
            $apicallsdir = __DIR__ . "/../fixtures/api_calls";
            $excludes = ['.', '..'];
            foreach (scandir($apicallsdir) as $method) {
                if (!in_array($method, $excludes)) {
                    $methoddir = $apicallsdir . "/" . $method;
                    foreach (scandir($methoddir) as $callfile) {
                        if (!in_array($callfile, $excludes)) {
                            $apicall = file_get_contents($methoddir . "/" . $callfile);
                            $apicall = json_decode($apicall);
                            $method = strtoupper($method);
                            $status = property_exists($apicall->status) ? intval($apicall->status) : 200;
                            $body = !empty($apicall->body) ? json_encode($apicall->body) : null;
                            $params = !empty($apicall->params) ? json_encode($apicall->params) : '';
                            $headers = !empty($apicall->headers) ? json_encode($apicall->headers) : [];
                            $apitestable->add_json_response($apicall->resource, $method, $status, $body, $params, $headers);
                        }
                    }
                }
            }
        }
        // This method from apitestable class must be called after all responses are added.
        $apitestable->set_opencastapi_mock_responses();
        return $apitestable;
    }
}
