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
        set_config('roles_1',
            '[{"rolename":"ROLE_ADMIN","actions":"write,read","permanent":1},' .
            '{"rolename":"ROLE_GROUP_MH_DEFAULT_ORG_EXTERNAL_APPLICATIONS","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEID]_Instructor","actions":"write,read","permanent":1},' .
            '{"rolename":"[COURSEGROUPID]_Learner","actions":"read","permanent":0}]',
            'block_opencast');
        set_config('metadata_1',
            '[{"name":"title","datatype":"text","required":1,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}"},' .
            '{"name":"subjects","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":1},' .
            '{"name":"description","datatype":"textarea","required":0,"readonly":0,' .
            '"param_json":"{\"rows\":\"3\",\"cols\":\"19\"}","defaultable":1},{"name":"language","datatype":"select",' .
            '"required":0,"readonly":0,"param_json":"{\"\":\"No option selected\",\"slv\":\"Slovenian\",\"por\":\"Portugese\",' .
            '\"roh\":\"Romansh\",\"ara\":\"Arabic\",\"pol\":\"Polish\",\"ita\":\"Italian\",\"zho\":\"Chinese\",' .
            '\"fin\":\"Finnish\",\"dan\":\"Danish\",\"ukr\":\"Ukrainian\",\"fra\":\"French\",\"spa\":\"Spanish\",' .
            '\"gsw\":\"Swiss German\",\"nor\":\"Norwegian\",\"rus\":\"Russian\",\"jpx\":\"Japanese\",\"nld\":\"Dutch\",' .
            '\"tur\":\"Turkish\",\"hin\":\"Hindi\",\"swa\":\"Swedish\",\"eng\":\"English\",\"deu\":\"German\"}","defaultable":0}' .
            ',{"name":"rightsHolder","datatype":"text","required":0,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}"' .
            ',"defaultable":0},{"name":"license","datatype":"select","required":0,"readonly":0,' .
            '"param_json":"{\"\":\"No option selected\",\"ALLRIGHTS\":\"All Rights Reserved\",\"CC0\":\"CC0\",' .
            '\"CC-BY-ND\":\"CC BY-ND\",\"CC-BY-NC-ND\":\"CC BY-NC-ND\",\"CC-BY-NC-SA\":\"CC BY-NC-SA\",' .
            '\"CC-BY-SA\":\"CC BY-SA\",\"CC-BY-NC\":\"CC BY-NC\",\"CC-BY\":\"CC BY\"}","defaultable":0},' .
            '{"name":"creator","datatype":"autocomplete","required":0,"readonly":0,"param_json":"","defaultable":0},' .
            '{"name":"contributor","datatype":"autocomplete","required":0,"readonly":0,"param_json":"","defaultable":0}]',
            'block_opencast');
        set_config('metadataseries_1',
            '[{"name":"title","datatype":"text","required":1,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}"},' .
            '{"name":"subjects","datatype":"autocomplete","required":0,"readonly":0,"param_json":null,"defaultable":0},' .
            '{"name":"description","datatype":"textarea","required":0,"readonly":0,"param_json":' .
            '"{\"rows\":\"3\",\"cols\":\"19\"}","defaultable":0},{"name":"language","datatype":"select","required":0,' .
            '"readonly":0,"param_json":"{\"\":\"No option selected\",\"slv\":\"Slovenian\",\"por\":\"Portugese\",\"roh\"' .
            ':\"Romansh\",\"ara\":\"Arabic\",\"pol\":\"Polish\",\"ita\":\"Italian\",\"zho\":\"Chinese\",\"fin\":\"Finnish\"' .
            ',\"dan\":\"Danish\",\"ukr\":\"Ukrainian\",\"fra\":\"French\",\"spa\":\"Spanish\",\"gsw\":\"Swiss German\",' .
            '\"nor\":\"Norwegian\",\"rus\":\"Russian\",\"jpx\":\"Japanese\",\"nld\":\"Dutch\",\"tur\":\"Turkish\",' .
            '\"hin\":\"Hindi\",\"swa\":\"Swedish\",\"eng\":\"English\",\"deu\":\"German\"}","defaultable":0},' .
            '{"name":"rightsHolder","datatype":"text","required":0,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}",' .
            '"defaultable":0},{"name":"license","datatype":"select","required":0,"readonly":0,"param_json":' .
            '"{\"\":\"No option selected\",\"ALLRIGHTS\":\"All Rights Reserved\",\"CC0\":\"CC0\",\"CC-BY-ND\":\"CC BY-ND\",' .
            '\"CC-BY-NC-ND\":\"CC BY-NC-ND\",\"CC-BY-NC-SA\":\"CC BY-NC-SA\",\"CC-BY-SA\":\"CC BY-SA\",\"CC-BY-NC\":' .
            '\"CC BY-NC\",\"CC-BY\":\"CC BY\"}","defaultable":0},{"name":"creator","datatype":"autocomplete","required":0,' .
            '"readonly":0,"param_json":null,"defaultable":0},{"name":"contributor","datatype":"autocomplete","required":0,' .
            '"readonly":0,"param_json":null,"defaultable":0}]',
            'block_opencast');
        set_config('transcriptionflavors_1',
            '[{"key":"de","value":"Amberscript German"},{"key":"en","value":"Amberscript English"},' .
            '{"key":"deu","value":"Vosk German"},{"key":"eng","value":"Vosk English"},{"key":"prf","value":"Persian"}]',
            'block_opencast');
        set_config('maxseries_1', 3, 'block_opencast');
        set_config('limitvideos_1', 5, 'block_opencast');
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
        $courses = core_course_category::search_courses(array('search' => 'Course 1'));
        $directaccesslink = '/blocks/opencast/directaccess.php?video_identifier=ID-coffee-run' .
            '&mediaid=34010ca7-374d-4cd9-91e1-51c49df195f7&ocinstanceid=1&courseid=' . reset($courses)->id;
        $this->execute('behat_general::i_visit', [$directaccesslink]);
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
     * adds a breakpoints
     * stops the execution until you hit enter in the console
     * @Then /^breakpoint in ocblock/
     */
    public function breakpoint_in_ocblock() {
        fwrite(STDOUT, "\033[s    \033[93m[Breakpoint] Press \033[1;93m[RETURN]\033[0;93m to continue...\033[0m");
        while (fgets(STDIN, 1024) == '') {
            continue;
        }
        fwrite(STDOUT, "\033[u");
        return;
    }
}
