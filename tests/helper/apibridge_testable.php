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

use tool_opencast\seriesmapping;

class block_opencast_apibridge_testable extends \block_opencast\local\apibridge {

    /** @var array register for possible function results. */
    private $register = [];

    const DUPLICATE_WORKFLOW = 'api_duplicate';

    /**
     * For basic testcases connection parameters are not necessary.
     * block_opencast_apibridge_testable constructor.
     */
    public function __construct() {

    }

    /**
     * Test access for the protected getroles function.
     * @return array
     * @throws dml_exception
     */
    public function getroles_testable() {
        return parent::getroles(1);
    }

    /**
     * Set data for the simulation of test data.
     *
     * @param string $methodname
     * @param strint $key
     *
     * @param mixed $value
     */
    public function set_testdata($methodname, $key, $value) {
        global $CFG;

        if (!isset($this->register[$methodname])) {
            $this->register[$methodname] = [];
        }

        if ($value === "file") {
            $value = file_get_contents($CFG->dirroot . "/blocks/opencast/tests/fixtures/$methodname.js");
        }
        $this->register[$methodname][$key] = $value;
    }

    /**
     * Get data necessary for apibridge methods below.
     *
     * @param string $methodname
     * @param string $key
     *
     * @return mixed the data or 'file' when using a file in /fixtures.
     */
    private function get_testdata($methodname, $key = null) {

        if (!isset($this->register[$methodname])) {
            return null;
        }

        if ($key == null) {
            return $this->register[$methodname];
        }

        if (!isset($this->register[$methodname][$key])) {
            return null;
        }

        return $this->register[$methodname][$key];
    }

    /**
     * Unset data for the simulation.
     *
     * @param string $methodname
     * @param mixed $key
     */
    public function unset_testdata($methodname, $key) {

        if (!isset($this->register[$methodname])) {
            return;
        }

        if (!isset($this->register[$methodname][$key])) {
            return;
        }

        unset($this->register[$methodname][$key]);
    }

    /**
     * Simulate a call to opencast to get course videos for unit test.
     *
     * @param int $courseid
     * @param string $sortcolumns
     *
     * @return object with error code and videos.
     */
    public function get_course_videos($courseid, $sortcolumns = null) {

        $result = new \stdClass();
        $result->videos = array();
        $result->error = 0;

        if (!$value = $this->get_testdata('get_course_videos', $courseid)) {
            return $result;
        }

        $result->videos = json_decode($value);
        return $result;
    }

    public function get_series_videos($series, $sortcolumns = null) {
        $result = new stdClass();
        $result->error = 0;

        if (!$value = $this->get_testdata('get_series_videos', $series)) {
            // Used for behat test.
            $video = new stdClass();
            $video->identifier = '1111-2222-3333-4444';
            $video->title = 'MyTitle';
            $video->series = 'My Test Series';
            $video->status = "EVENTS.EVENTS.STATUS.PROCESSED";
            $video->processing_state = "SUCCEEDED";
            $video->created = "2021-07-31T09:55:00Z";
            $video->start = "2021-07-31T09:55:00Z";
            $video->is_part_of = '1234-5678-abcd-efgh';
            $video->is_downloadable = false;
            $video->location = '';
            $video->publication_status = array();
            $result->videos = [$video];
        } else {
            $result->videos = json_decode($value);
        }

        return $result;
    }

    public function get_block_videos($courseid) {
        // Used for behat test.
        $result = new \stdClass();
        $result->count = 0;
        $result->more = false;
        $result->videos = array();
        $result->error = 0;

        $series = seriesmapping::get_record(array('courseid' => $courseid, 'isdefault' => 1));
        if ($series) {
            $video = new stdClass();
            $video->identifier = '1111-2222-3333-4444';
            $video->title = 'MyTitle';
            $video->series = 'My Test Series';
            $video->status = "EVENTS.EVENTS.STATUS.PROCESSED";
            $video->processing_state = "SUCCEEDED";
            $video->created = "2021-07-31T09:55:00Z";
            $video->start = "2021-07-31T09:55:00Z";
            $video->is_part_of = '1234-5678-abcd-efgh';
            $video->is_downloadable = false;
            $video->location = '';
            $video->publication_status = array();

            $result->count = 1;
            $result->videos = [$video];
        }

        return $result;
    }

    /**
     * Simulate calling opencast for creating a course series.
     *
     * @param int $courseid
     * @param string $seriestitle
     * @return boolean
     * @throws \moodle_exception
     */
    public function create_course_series($courseid, $seriestitle = null, $userid = null) {

        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'isdefault' => '1'));
        if ($mapping && $seriesid = $mapping->get('series')) {
            throw new \moodle_exception(get_string('series_already_exists', 'block_opencast', $seriesid));
        }

        // Simulate new series.
        if ($identifier = $this->get_testdata('create_course_series', 'newcourse')) {
            $series = (object)[
                'identifier' => $identifier
            ];
        }

        if (isset($series) && object_property_exists($series, 'identifier')) {
            $mapping = new seriesmapping();
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $series->identifier);
            $mapping->set('isdefault', '1');
            $mapping->set('ocinstanceid', 1);
            $mapping->create();
            return true;
        }
        return false;
    }

    /**
     * Simulate a call to opencast for checking level of api.
     *
     * @param string $level
     * @return boolean
     */
    public function supports_api_level($level) {
        $value = $this->get_testdata('supports_api_level', 'level');
        return ($value == $level);
    }

    /**
     * Simulate a call to opencast for checking, if event exists.
     *
     * @param array $eventids
     * @return boolean
     */
    public function get_already_existing_event($eventids) {

        $registeredevents = $this->get_testdata('get_course_videos');

        foreach ($eventids as $eventid) {
            foreach ($registeredevents as $jsonvalue) {
                if (strpos($jsonvalue, '"' . $eventid . '"') > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function get_default_course_series($courseid) {

        $result = null;
        if ($value = $this->get_testdata('get_default_course_series', $courseid)) {
            return (object)['identifier' => $value];
        }

        return $result;
    }

    public function check_if_workflow_exists($name) {
        return $this->get_testdata('check_if_workflow_exists', $name);
    }

    public function start_workflow($eventid, $duplicateworkflow, $params = array(), $returnworkflowid = false) {
        return $this->get_testdata('start_workflow', $duplicateworkflow);
    }

}
