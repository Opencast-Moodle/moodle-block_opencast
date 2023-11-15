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
use block_opencast\local\apibridge;
use block_opencast\setting_default_manager;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps definitions related with the opencast blocke.
 *
 * @copyright 2021 Tamara Gunkel, University of Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_opencast extends behat_base {

    /**
     * @var $directaccesslink string direct access link to be saved temporarily, and then be used when needed. (i.e. in the step
     * where student wants to access that link).
     */
    private $directaccesslink;

    /**
     * Setup test opencast API.
     * @Given /^I setup the opencast test api$/
     */
    public function i_setup_the_opencast_test_api() {
        set_config('api_testable_responses', '[]', 'tool_opencast');
        $this->load_apitestable_json_responses();
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
    }

    /**
     * Setup default settings for the block
     * @Given /^I setup the default settigns for opencast plugins$/
     */
    public function i_setup_the_default_settings_for_opencast_plugins() {
        setting_default_manager::init_regirstered_defaults(1);
    }

    /**
     * adds a breakpoints
     * stops the execution until you hit enter in the console
     * @Then /^breakpoint/
     */
    public function breakpoint() {
        fwrite(STDOUT, "\033[s    \033[93m[Breakpoint] Press \033[1;93m[RETURN]\033[0;93m to continue...\033[0m");
        while (fgets(STDIN, 1024) == '') {
            continue;
        }
        fwrite(STDOUT, "\033[u");
        return;
    }

    /**
     * Loads the json responses taken from fixtures dir into apitestable config.
     */
    protected function load_apitestable_json_responses() {
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
                        $status = !empty($apicall->status) ? intval($apicall->status) : 200;
                        $body = !empty($apicall->body) ? json_encode($apicall->body) : null;
                        $params = !empty($apicall->params) ? json_encode($apicall->params) : '';
                        $headers = !empty($apicall->headers) ? json_encode($apicall->headers) : [];
                        api_testable::add_json_response($apicall->resource, $method, $status, $body, $params, $headers);
                    }
                }
            }
        }
    }

    /**
     * Opens the video's direct access link
     * @Given /^I go to direct access link$/
     */
    public function i_go_to_direct_access_link() {
        // Get the direct access link.
        if (empty($this->directaccesslink)) {
            $csselement = '#opencast-videos-table-ID-blender-foundation_r0 .c3 .access-action-menu a.access-link-copytoclipboard';
            try {
                $this->find('css', $csselement);
            } catch (ElementNotFoundException $e) {
                throw new ExpectationException('Targeted Element to copy direct access link could not be found.');
            }
            $element = $this->find('css', $csselement);
            $this->directaccesslink = $element->getAttribute('href');
        }
        $this->execute('behat_general::i_visit', [$this->directaccesslink]);
    }

    /**
     * Checks if there is no video in processing stage (mainly used for republish-metadata workflow).
     * @Given /^I wait until no video is being processed$/
     */
    public function i_wait_until_no_video_is_being_processed() {
        $courses = core_course_category::search_courses(array('search' => 'Course 1'));

        $mappedseries = seriesmapping::get_records(array('ocinstanceid' => 1, 'courseid' => reset($courses)->id));
        $series = reset($mappedseries)->get('series');
        $apibridge = \block_opencast\local\apibridge::get_instance(1);
        do {
            $videos = $apibridge->get_series_videos($series);
            $hasprocessing = false;
            foreach ($videos->videos as $video) {
                if ($video->processing_state == 'RUNNING') {
                    $hasprocessing = true;
                }
            }
            sleep(2);
        } while ($hasprocessing);
        return;
    }

    /**
     * Makes sure that opencast video is available in opencast
     * @Given /^I should watch the video in opencast$/
     */
    public function i_should_watch_the_video_in_opencast() {
        $xpath = "//video";
        $this->execute('behat_general::should_exist', array($xpath, 'xpath_element'));
    }

    /**
     * Checks whether the given lti tool has the given custom parameter.
     *
     * @Then /^the lti tool "([^"]*)" should have the custom parameter "([^"]*)"$/
     *
     * @param string $ltitoolname
     * @param string $customparameter
     */
    public function the_lti_tool_should_have_the_custom_parameter($ltitoolname, $customparameter) {
        global $DB;

        $ltitool = $DB->get_record('lti', ['name' => $ltitoolname]);
        if ($ltitool->instructorcustomparameters !== $customparameter) {
            throw new ExpectationException("$ltitoolname has custom parameter \"$ltitool->instructorcustomparameters\"" .
                    " instead of expected \"$customparameter\"", $this->getSession());
        }
    }

    /**
     * Checks whether the given lti tool has the given custom parameter.
     *
     * @Then /^the lti tool "([^"]*)" in the course "([^"]*)" should have the custom parameter "([^"]*)"$/
     *
     * @param string $ltitoolname
     * @param string $course
     * @param string $customparameter
     */
    public function the_lti_tool_in_the_course_should_have_the_custom_parameter($ltitoolname, $course, $customparameter) {
        global $DB;

        $cid = $this->get_course_id($course);

        $ltitool = $DB->get_record('lti', ['name' => $ltitoolname, 'course' => $cid]);
        if ($ltitool->instructorcustomparameters !== $customparameter) {
            throw new ExpectationException("$ltitoolname has custom parameter \"$ltitool->instructorcustomparameters\"".
                    " instead of expected \"$customparameter\"", $this->getSession());
        }
    }
}
