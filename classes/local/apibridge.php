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
 * API-bridge for opencast. Contain all the function, which uses the external API.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

use block_opencast\groupaccess;
use block_opencast\opencast_connection_exception;
use block_opencast_renderer;
use tool_opencast\seriesmapping;
use tool_opencast\local\api;
use block_opencast\opencast_state_exception;

require_once($CFG->dirroot . '/lib/filelib.php');
require_once(__DIR__ . '/../../renderer.php');
require_once($CFG->dirroot . '/blocks/opencast/tests/helper/apibridge_testable.php');

/**
 * API-bridge for opencast. Contain all the function, which uses the external API.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apibridge
{
    /** @var mixed Block settings */
    private $config;

    /** @var bool True for tests */
    private static $testing = false;

    /**
     * apibridge constructor.
     */
    private function __construct()
    {
        $this->config = get_config('block_opencast');
    }

    /**
     * Get an instance of an object of this class. Create as a singleton.
     * @param boolean $forcenewinstance true, when a new instance should be created.
     * @return apibridge
     */
    public static function get_instance($forcenewinstance = false)
    {
        static $apibridge;

        if (isset($apibridge) && !$forcenewinstance) {
            return $apibridge;
        }

        // Use replacement of api bridge for test cases.
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && self::$testing) {
            $apibridge = new \block_opencast_apibridge_testable();
            return $apibridge;
        }

        $apibridge = new apibridge();

        return $apibridge;
    }

    /**
     * Check, whether the Opencast API has been setup correctly.
     * This does not check if the Opencast server is up and running.
     * It just checks if the Opencast API configuration is fine by requesting an instance of the Opencast API from tool_opencast.
     *
     * @return boolean
     */
    public function check_api_configuration()
    {
        // Try to get an instance of the Opencast API from tool_opencast.
        try {
            $api = $this->get_instance();

            // If the API is not set up correctly, the constructor will throw an exception.
        } catch (\moodle_exception $e) {
            return false;
        }

        // Otherwise the API should be set up correctly.
        return true;
    }

    /**
     * Get videos to show in block. Items are limited and ready to use by renderer.
     * Note that we try to receive one item more than configurated to decide whether
     * to display a "more videos" link.
     *
     * @param int $courseid
     * @return \stdClass
     */
    public function get_block_videos($courseid)
    {

        $result = new \stdClass();
        $result->count = 0;
        $result->more = false;
        $result->videos = array();
        $result->error = 0;

        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'isdefault' => '1'));

        if (!$mapping || !($seriesid = $mapping->get('series'))) {
            return $result;
        }

        $seriesfilter = "series:" . $seriesid;

        $query = 'sign=1&withacl=1&withmetadata=1&withpublications=1&sort=start_date:DESC&filter=' . urlencode($seriesfilter);

        if ($this->config->limitvideos > 0) {
            // Try to fetch one more to decide whether display "more link" is necessary.
            $query .= '&limit=' . ($this->config->limitvideos + 1);
        }

        $url = '/api/events?' . $query;

        $withroles = array();

        $api = new api();

        $videos = $api->oc_get($url, $withroles);

        if ($api->get_http_code() === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($api->get_http_code() != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }

        if (!$videos = json_decode($videos)) {
            return $result;
        }

        $result->count = count($videos);
        $result->more = ($result->count > $this->config->limitvideos);

        // If we have received more than limit count of videos remove one.
        if ($result->more) {
            array_pop($videos);
        }

        if ($result->error == 0) {
            foreach ($videos as $video) {
                $this->extend_video_status($video);
            }
        }

        $result->videos = $videos;

        return $result;
    }

    /**
     * Get all the videos (events) for a course.
     * Note that they are restricted by course role.
     *
     * @param int $courseid
     * @param string $sortcolumns
     * @return array
     */
    public function get_course_videos($courseid, $sortcolumns = null) {
    // todo  check where this method is used and if this is fine to be used with the new series funciton.
        $result = new \stdClass();
        $result->videos = array();
        $result->error = 0;

        $series = $this->get_course_series($courseid);

        if (!isset($series)) {
            return $result;
        }
        $seriesfilter = "series:" . $series->identifier;

        $query = 'sign=1&withacl=1&withmetadata=1&withpublications=true&filter=' . urlencode($seriesfilter);
        if ($sortcolumns) {
            $sort = api::get_sort_param($sortcolumns);
            $query .= $sort;
        }

        $resource = '/api/events?' . $query;

        $withroles = array();

        $api = new api();
        $videos = $api->oc_get($resource, $withroles);

        if ($api->get_http_code() != 200) {
            $result->error = $api->get_http_code();

            return $result;
        }

        if (!$videos = json_decode($videos)) {
            return $result;
        }

        $result->videos = $videos;

        if ($result->error == 0) {
            foreach ($videos as $video) {
                $this->extend_video_status($video);
                $this->set_download_state($video);
            }
        }

        $result->videos = $videos;

        return $result;
    }

    /**
     * Get all the videos (events) for a series.
     *
     * @param string $series
     * @param string $sortcolumns
     * @return array
     */
    public function get_series_videos($series, $sortcolumns = null) {

        $result = new \stdClass();
        $result->videos = array();
        $result->error = 0;

        $seriesfilter = "series:" . $series;

        $query = 'sign=1&withacl=1&withmetadata=1&withpublications=true&filter=' . urlencode($seriesfilter);
        if ($sortcolumns) {
            $sort = api::get_sort_param($sortcolumns);
            $query .= $sort;
        }

        $resource = '/api/events?' . $query;

        $withroles = array();

        $api = new api();
        $videos = $api->oc_get($resource, $withroles);

        if ($api->get_http_code() != 200) {
            $result->error = $api->get_http_code();

            return $result;
        }

        if (!$videos = json_decode($videos)) {
            return $result;
        }

        $result->videos = $videos;

        if ($result->error == 0) {
            foreach ($videos as $video) {
                $this->extend_video_status($video);
                $this->set_download_state($video);
            }
        }

        $result->videos = $videos;

        return $result;
    }

    /**
     * Extend the state of the video and set the processing state accordingly.
     * Possibilities are the states:
     * - Planned
     * - Capturing
     * - In cutting
     * @param \stdClass $video The video object, which should be checked.
     */
    private function extend_video_status(&$video)
    {
        if ($video->status === "EVENTS.EVENTS.STATUS.PROCESSED" && $video->has_previews == true
            && count($video->publication_status) == 1 && $video->publication_status[0] == "internal") {
            $video->processing_state = "NEEDSCUTTING";
        } else if ($video->status === "EVENTS.EVENTS.STATUS.SCHEDULED") {
            $video->processing_state = "PLANNED";
        } else if ($video->status === "EVENTS.EVENTS.STATUS.RECORDING") {
            $video->processing_state = "CAPTURING";
        } else if ($video->status === "EVENTS.EVENTS.STATUS.INGESTING" ||
            $video->status === "EVENTS.EVENTS.STATUS.PENDING") {
            $video->processing_state = "RUNNING";
        } else if ($video->status === "EVENTS.EVENTS.STATUS.PROCESSED") {
            $video->processing_state = "SUCCEEDED";
        }
    }

    /**
     * Checks if a video can be downloaded and saves this state.
     * @param \stdClass $video Video to be updated
     */
    private function set_download_state(&$video)
    {
        if (in_array(get_config('block_opencast', 'download_channel'), $video->publication_status)) {
            $video->is_downloadable = true;
        } else {
            $video->is_downloadable = false;
        }
    }

    /**
     * Retrieves a video from Opencast.
     * @param string $identifier Event id
     * @param bool $withpublications If true, publications are included
     * @return \stdClass Video
     */
    public function get_opencast_video($identifier, bool $withpublications = false)
    {
        $resource = '/api/events/' . $identifier;

        if ($withpublications) {
            $resource .= '?withpublications=true';
        }

        $withroles = array();

        $api = new api();

        $video = $api->oc_get($resource, $withroles);

        $result = new \stdClass();
        $result->video = false;
        $result->error = 0;

        if ($api->get_http_code() != 200) {
            $result->error = $api->get_http_code();

            return $result;
        }

        if (!$video = json_decode($video)) {
            return $result;
        }

        // Enrich processing state.
        $this->extend_video_status($video);
        $this->set_download_state($video);

        $result->video = $video;

        return $result;
    }

    /**
     * API call to check, whether the course related group exists in opencast system.
     *
     * @param int $courseid
     * @return object group object of NULL, if group does not exist.
     */
    protected function get_acl_group($courseid, $userid)
    {
        $groupname = $this->replace_placeholders(get_config('block_opencast', 'group_name'), $courseid, null, $userid)[0];
        $groupidentifier = $this->get_course_acl_group_identifier($groupname);

        $api = new api();
        $group = $api->oc_get('/api/groups/' . $groupidentifier);

        return json_decode($group);
    }

    /**
     * Returns the group identifier from a group name.
     *
     * @param String $groupname
     * @return mixed
     */
    private function get_course_acl_group_identifier($groupname)
    {
        $groupidentifier = mb_strtolower($groupname, 'UTF-8');

        return preg_replace('/[^a-zA-Z0-9_]/', '_', $groupidentifier);
    }

    /**
     * API call to create a group for given course.
     *
     * @param int $courseid
     * @return object group object of NULL, if group does not exist.
     */
    protected function create_acl_group($courseid, $userid)
    {
        $params = [];
        $params['name'] = $this->replace_placeholders(get_config('block_opencast', 'group_name'), $courseid, null, $userid)[0];
        $params['description'] = 'ACL for users in Course with id ' . $courseid . ' from site "Moodle"';
        $params['roles'] = 'ROLE_API_SERIES_VIEW,ROLE_API_EVENTS_VIEW';
        $params['members'] = '';

        $api = new api();

        $result = $api->oc_post('/api/groups', $params);

        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }

        return $result;
    }

    /**
     * Check, whether the related group exists to given course id. If not exists thatn try to create
     * a group in opencast system.
     *
     * @param int $courseid
     * @return object group object.
     * @throws opencast_state_exception
     */
    public function ensure_acl_group_exists($courseid, $userid)
    {

        $group = $this->get_acl_group($courseid, $userid);

        if (!isset($group->identifier)) {
            $this->create_acl_group($courseid, $userid);
            // Check success.
            $group = $this->get_acl_group($courseid, $userid);
        }

        if (!isset($group->identifier)) {
            throw new opencast_state_exception('missinggroup', 'block_opencast');
        }

        return $group;
    }

    /**
     * Persist the new groups for the eventid;
     * @param string $eventid id of the event
     * @param int[] $groups ids of all groups for which access should be provided.
     * If $groups is empty the access is not restricted.
     * @return bool
     */
    private function store_group_access($eventid, $groups)
    {
        try {
            $groupaccess = groupaccess::get_record(array('opencasteventid' => $eventid));
            if ($groupaccess) {
                if (empty($groups)) {
                    $groupaccess->delete();
                } else {
                    $groupaccess->set('groups', implode(',', $groups));
                    $groupaccess->update();
                }
            } else {
                $groupaccess = new groupaccess();
                $groupaccess->set('opencasteventid', $eventid);
                $groupaccess->set('groups', implode(',', $groups));
                $groupaccess->create();
            }
        } catch (\moodle_exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Retrieves the id of the series, which is stored in the admin tool.
     *
     * @param int $courseid id of the course.
     * @param bool $createifempty Create a series on-the-fly if there isn't a series stored yet.
     * @return string id of the series
     */
    public function get_stored_seriesid($courseid, $createifempty = false, $userid = null)
    {
        // Get series mapping.
        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'isdefault' => '1'));

        // Get existing series from the series, set it to null if there isn't an existing mapping or series in the mapping.
        if (!$mapping || !($seriesid = $mapping->get('series'))) {
            $seriesid = null;
        }

        // If no series exists and if requested, ensure that a series exists.
        if ($seriesid == null && $createifempty == true) {
            // Create a series on-the-fly.
            $seriescreated = $this->create_course_series($courseid, null, $userid);

            // The series was created.
            if ($seriescreated == true) {
                // Fetch the created series' id.
                $seriesid = $this->get_stored_seriesid($courseid);

                // Otherwise there must have been some problem.
            } else {
                // Remember series id as null.
                $seriesid = null;
            }
        }

        // Return series id.
        return $seriesid;
    }

    /**
     * API call to check, whether series exists in opencast system.
     *
     * @param int $seriesid
     * @return null|string id of the series id if it exists in the opencast system.
     */
    public function get_series_by_identifier($seriesid)
    {

        $url = '/api/series/' . $seriesid;

        $api = new api();

        $series = $api->oc_get($url);

        return json_decode($series);
    }

    /**
     * API call to check, whether series exists in opencast system.
     *
     * @param int $seriesid
     * @return null|string id of the series id if it exists in the opencast system.
     */
    public function get_multiple_series_by_identifier($allseries) {

        $url = '/api/series?' ;

        $params = array();
        foreach($allseries as $series){
            $params[] = 'identifier='.$series->series;
        }

        $url .= implode("&", $params);

        $api = new api();

        $series = $api->oc_get($url);

        return json_decode($series);
    }

    /**
     * API call to check, whether the course related series exists in opencast system.
     *
     * @param int $courseid
     * @return null|string id of the series id if it exists in the opencast system.
     */
    public function get_course_series($courseid)
    {

        if ($seriesid = $this->get_stored_seriesid($courseid)) {
            $url = '/api/series/' . $seriesid;

            $api = new api();

            $series = $api->oc_get($url);

            return json_decode($series);
        }
        return null;
    }

    /**
     * Replaces the placeholders [COURSENAME], [COURSEID] and [COURSEGROUPID].
     * In case of the last one, there are two cases:
     *  1. if the event is restricted by group, the function returns one entry per group,
     *     where the placeholder is replaced by a 'G' followed by the group id.
     *  2. if the event is not restricted by group, the placeholder is simply replaced by the course id.
     *
     * @param string $name name of the rule, in which the placeholders should be replaced.
     * @param int $courseid id of the course, for which acl rules should be genereated.
     * @param array|null $groups the groups for replacement by [COURSEGROUPID].
     *
     * @return string[]
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function replace_placeholders($name, $courseid, $groups = null, $userid = null)
    {
        global $DB;

        $coursename = get_course($courseid)->fullname;
        $title = str_replace('[COURSENAME]', $coursename, $name);
        $title = str_replace('[COURSEID]', $courseid, $title);

        if (strpos($title, '[USERNAME]') !== false || strpos($title, '[USERNAME_LOW]') !== false ||
            strpos($title, '[USERNAME_UP]') !== false) {
            if (!$userid) {
                return array();
            }
            $username = $DB->get_record("user", array("id" => $userid))->username;
            $title = str_replace('[USERNAME]', $username, $title);
            $title = str_replace('[USERNAME_LOW]', strtolower($username), $title);
            $title = str_replace('[USERNAME_UP]', strtoupper($username), $title);
        }

        $result = array();

        if (strpos($name, '[COURSEGROUPID]') !== false) {
            if ($groups) {
                foreach ($groups as $groupid) {
                    $result [] = str_replace('[COURSEGROUPID]', 'G' . $groupid, $title);
                }
            } else {
                $result [] = str_replace('[COURSEGROUPID]', $courseid, $title);
            }
        } else {
            $result [] = $title;
        }

        return $result;
    }

    /**
     * The function returns a needle for a search among a set of acl. The goal is to check,
     * if there are any group related acl rules.
     * @param string $name Role name
     * @param int $courseid Course id
     * @return string Role name with substituted placeholders.
     */
    private function get_pattern_for_group_placeholder($name, $courseid)
    {
        $coursename = get_course($courseid)->fullname;
        $title = str_replace('[COURSENAME]', $coursename, $name);
        $title = str_replace('[COURSEID]', $courseid, $title);
        return '/' . str_replace('[COURSEGROUPID]', 'G\\d*', $title) . '/';
    }

    /**
     * Returns the default series name for a course.
     * @param int $courseid id of the course.
     * @return string default series title.
     */
    public function get_default_seriestitle($courseid, $userid)
    {
        $title = get_config('block_opencast', 'series_name');
        return $this->replace_placeholders($title, $courseid, null, $userid)[0];
    }

    /**
     * API call to create a series for given course.
     * @param int $courseid Course id
     * @param null|string $seriestitle Series title
     * @return bool  tells if the creation of the series was successful.
     */
    public function create_course_series($courseid, $seriestitle = null, $userid = null) {
        // TODO check if new implementation works with other places where this method is used.

        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'isdefault' => '1'));
        $isdefault = true;
        if ($mapping) {
            $isdefault = false;
        }

        $params = [];

        $metadata = array();
        $metadata['label'] = "Opencast Series Dublincore";
        $metadata['flavor'] = "dublincore/series";
        $metadata['fields'] = [];

        if (is_null($seriestitle)) {
            $title = $this->get_default_seriestitle($courseid, $userid);
        } else {
            $title = $seriestitle;
        }

        $metadata['fields'][] = array('id' => 'title', 'value' => $title);

        $params['metadata'] = json_encode(array($metadata));

        $acl = array();
        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $acl[] = (object)array('allow' => true, 'action' => $action,
                    'role' => $this->replace_placeholders($role->rolename, $courseid, null, $userid)[0]);
            }
        }

        $params['acl'] = json_encode(array_values($acl));
        $params['theme'] = '';

        $api = new api();

        $result = $api->oc_post('/api/series', $params);

        if ($api->get_http_code() >= 400 | $api->get_http_code() < 200) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }

        $series = json_decode($result);
        if (isset($series) && object_property_exists($series, 'identifier')) {
            $mapping = new seriesmapping();
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $series->identifier);
            $mapping->set('isdefault', $isdefault);
            $mapping->create();
            $rec = $mapping->to_record();
            $rec->seriestitle = $seriestitle;
            return $rec;
        }
        return false;
    }

    /**
     * Check, whether the related series exists to given course id. If not exists than try to create
     * a group in opencast system.
     *
     * @param int $courseid
     *
     * @return object series object.
     * @throws opencast_state_exception
     */
    public function ensure_course_series_exists($courseid, $userid)
    {

        $series = $this->get_course_series($courseid);

        if (!isset($series)) {
            $this->create_course_series($courseid, null, $userid);
            // Check success.
            $series = $this->get_course_series($courseid);
        }

        if (!isset($series)) {
            throw new opencast_state_exception('missingseries', 'block_opencast');
        }

        return $series;
    }

    /**
     * Defines a new series ID for a course.
     *
     * @param int $courseid Course ID
     * @param string $seriesid Series ID
     */
    public function update_course_series($courseid, $seriesid, $userid) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'isdefault' => '1'));

        if (!$mapping) {
            $mapping = new seriesmapping();
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $seriesid);
            $mapping->set('isdefault', '1');
            $mapping->create();
        } else {
            $mapping->set('series', $seriesid);
            $mapping->update();
        }

        // Update Acl roles.
        $api = new api();
        $resource = '/api/series/' . $seriesid . '/acl';
        $jsonacl = $api->oc_get($resource);

        $acl = json_decode($jsonacl);

        if (!is_array($acl)) {
            throw new \moodle_exception('invalidacldata', 'block_opencast');
        }

        $roles = $this->getroles();
        foreach ($roles as $role) {
            if (strpos($role->rolename, '[USERNAME]') !== false ||
                strpos($role->rolename, '[USERNAME_LOW]') !== false ||
                strpos($role->rolename, '[USERNAME_UP]') !== false) {
                // Add new user as well.
                foreach ($role->actions as $action) {
                    $acl[] = (object)array('allow' => true,
                        'role' => $this->replace_placeholders($role->rolename, $courseid, null, $userid)[0],
                        'action' => $action);
                }

            } else {
                foreach ($role->actions as $action) {
                    foreach ($acl as $key => $aclval) {
                        if (($aclval->action == $action) && ($aclval->role == $role)) {
                            unset($acl[$key]);
                        }
                    }

                    $acl[] = (object)array('allow' => true,
                        'role' => $this->replace_placeholders($role->rolename, $courseid)[0],
                        'action' => $action);
                }
            }
        }

        $params['acl'] = json_encode(array_values($acl));

        // Acl roles have not changed.
        if ($params['acl'] == ($jsonacl)) {
            return true;
        }

        $api = new api();

        $api->oc_put($resource, $params);

        return ($api->get_http_code() == 204);
    }

    /**
     * Remove course series ID, because it was set blank.
     * No changes in Opencast are done, due to this action.
     *
     * @param int $courseid Course ID
     */
    public function unset_course_series($courseid) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'isdefault' => '1'));

        if ($mapping) {
            $mapping->delete();
        }
    }

    /**
     * Checks if the series ID exists in the Opencast system.
     * @param string $seriesid Series id
     * @return bool true, if the series exists. Otherwise false.
     * @throws \dml_exception
     * @throws \moodle_exception if there is no connection to the server.
     */
    public function ensure_series_is_valid($seriesid)
    {
        $api = new api();
        $api->oc_get('/api/series/' . $seriesid);

        if ($api->get_http_code() === 404) {
            return false;
        }

        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }

        return true;
    }

    /**
     * API call to check, whether at least one already uploaded event exists.
     *
     * @param array $opencastids
     *
     * @return mixed false or existing event.
     */
    public function get_already_existing_event($opencastids)
    {

        foreach ($opencastids as $opencastid) {

            $resource = '/api/events/' . $opencastid;

            $api = new api();

            $event = $api->oc_get($resource);
            $event = json_decode($event);

            if (isset($event) && isset($event->identifier)) {
                return $event;
            }
        }

        return false;
    }

    /**
     * API call to create an event.
     * @param object $job Event to be created
     * @return object series object of NULL, if group does not exist.
     */
    public function create_event($job) {
        global $DB;

        $event = new \block_opencast\local\event();

        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $event->add_acl(true, $action, $this->replace_placeholders($role->rolename, $job->courseid, null, $job->userid)[0]);
            }
        }
        // Applying the media types to the event.
        $validstoredfile = true;
        if ($job->presenter_fileid) {
            $event->set_presenter($job->presenter_fileid);
            if (!$event->get_presenter()) {
                $validstoredfile = false;
            }
        }
        if ($job->presentation_fileid) {
            $event->set_presentation($job->presentation_fileid);
            if (!$event->get_presentation()) {
                $validstoredfile = false;
            }
        }
        if ($job->chunkupload_presenter) {
            $event->set_chunkupload_presenter($job->chunkupload_presenter);
            if (!$event->get_presenter()) {
                $validstoredfile = false;
            }
        }
        if ($job->chunkupload_presentation) {
            $event->set_chunkupload_presentation($job->chunkupload_presentation);
            if (!$event->get_presentation()) {
                $validstoredfile = false;
            }
        }

        if (!$validstoredfile) {
            $DB->delete_records('block_opencast_uploadjob', ['id' => $job->id]);
            throw new \moodle_exception('invalidfiletoupload', 'tool_opencast');
        }

        if ($job->metadata) {
            foreach (json_decode($job->metadata) as $metadata) {
                $event->add_meta_data($metadata->id, $metadata->value);
            }
        }
        // Todo check other method that are calling this one.
     //   $event->add_meta_data('isPartOf', $seriesidentifier);
        $params = $event->get_form_params();

        $api = new api();

        $result = $api->oc_post('/api/events', $params);

        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }

        return $result;
    }

    /**
     *
     * Returns an array of acl roles. The actions field of each entry contains an array of trimmed action names
     * for the specific role.
     *
     * @param null $conditions
     *
     * @return array of acl roles.
     * @throws \dml_exception A DML specific exception is thrown for any errors.
     */
    public function getroles($permanent = null)
    {
        $roles = json_decode(get_config('block_opencast', 'roles'));
        $rolesprocessed = [];
        foreach ($roles as $role) {
            if ($permanent === null || $permanent === $role->permanent) {
                $rolesprocessed[] = $role;
                $role->actions = array_map('trim', explode(',', $role->actions));
            }
        }

        return $rolesprocessed;
    }

    /**
     * Check, whether the related series exists to given course id. If not exists than try to create
     * a group in opencast system.
     *
     * @param \stdClass $job Job to be checked
     * @param array $opencastids Opencas id
     * @return object (Created) event
     */
    public function ensure_event_exists($job, $opencastids) {

        if ($opencastids) {
            if ($event = $this->get_already_existing_event($opencastids)) {
                // Flag as existing event.
                $event->newlycreated = false;

                return $event;
            }
        }

        $event = $this->create_event($job);

        // Check success.
        if (!$event) {
            throw new opencast_state_exception('uploadingeventfailed', 'block_opencast');
        }

        $event = json_decode($event);
        // Flag as newly created.
        $event->newlycreated = true;

        return $event;
    }

    /**
     * Post group to control access.
     *
     * @param string $eventidentifier
     * @param int $courseid
     *
     * @return boolean true if succeeded
     */
    public function ensure_acl_group_assigned($eventidentifier, $courseid, $userid)
    {
        $api = new api();
        $resource = '/api/events/' . $eventidentifier . '/acl';
        $jsonacl = $api->oc_get($resource);

        $event = new \block_opencast\local\event();
        $event->set_json_acl($jsonacl);

        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                foreach ($this->replace_placeholders($role->rolename, $courseid, $eventidentifier, $userid) as $acl) {
                    $event->add_acl(true, $action, $acl);
                }
            }
        }

        $resource = '/api/events/' . $eventidentifier . '/acl';
        $params['acl'] = $event->get_json_acl();

        // Acl roles have not changed.
        if ($params['acl'] == ($jsonacl)) {
            return true;
        }

        $api = new api();

        $api->oc_put($resource, $params);

        if ($api->get_http_code() != 204) {
            return false;
        }

        // Trigger workflow.
        return $this->update_metadata($eventidentifier);
    }

    /**
     * Can delete the acl group assignment.
     * @param object $video opencast video.
     * @param int $courseid Course id
     * @return bool If acl group can be deleted
     */
    public function can_delete_acl_group_assignment($video, $courseid)
    {

        $config = get_config('block_opencast', 'allowunassign');

        if (!$config) {
            return false;
        }

        if (!isset($video->processing_state) || ($video->processing_state != 'SUCCEEDED')) {
            return false;
        }

        $context = \context_course::instance($courseid);

        return has_capability('block/opencast:unassignevent', $context);
    }

    /**
     * Remove the group role assignment for the event.
     *
     * @param string $eventidentifier
     * @param int $courseid
     *
     * @return boolean true if succeeded
     */
    public function delete_acl_group_assigned($eventidentifier, $courseid)
    {
        $event = new \block_opencast\local\event();

        $grouprole = api::get_course_acl_role($courseid);
        $resource = '/api/events/' . $eventidentifier . '/acl/read/' . $grouprole;

        $api = new api();
        $api->oc_delete($resource);

        if ($api->get_http_code() != 204) {
            return false;
        }

        $resource = '/api/events/' . $eventidentifier . '/acl';
        $acls = $api->oc_get($resource);
        if ($api->get_http_code() != 200) {
            return false;
        }
        $event->set_json_acl($acls);

        // Adapt course series.
        if (!$courseid = $event->get_next_series_courseid()) {
            $this->ensure_series_assigned($eventidentifier, '');
        }

        $series = $this->ensure_course_series_exists($courseid, null);

        return $this->ensure_series_assigned($eventidentifier, $series->identifier);
    }

    /**
     * Changes the visibility of videos by updating the ACL list.
     * @param string $eventidentifier Event id
     * @param int $courseid Course id
     * @param int $visibility Visibility to be applied
     * @param array|null $groups Groups
     * @return string identifier of the notification string to be presented to the user.
     */
    public function change_visibility($eventidentifier, $courseid, $visibility, $groups = null)
    {
        $oldgroups = groupaccess::get_record(array('opencasteventid' => $eventidentifier));
        $oldgroupsarray = $oldgroups ? explode(',', $oldgroups->get('groups')) : array();

        $allowedvisibilitystates = array(block_opencast_renderer::VISIBLE,
            block_opencast_renderer::HIDDEN, block_opencast_renderer::GROUP);
        if (!in_array($visibility, $allowedvisibilitystates)) {
            throw new \coding_exception('Invalid visibility state.');
        }

        $oldvisibility = $this->is_event_visible($eventidentifier, $courseid);

        // Only use transmitted groups if the status is group.
        if ($visibility !== \block_opencast_renderer::GROUP) {
            $groups = array();
        }

        // If there is no change in the status or in the group arrays, we can stop here.
        if ($oldvisibility === $visibility) {
            if ($visibility !== \block_opencast_renderer::GROUP || $groups === $oldgroupsarray) {
                return 'aclnothingtobesaved';
            }
        }

        // Update group access.
        if ($groups !== $oldgroupsarray) {
            $this->store_group_access($eventidentifier, $groups);
        }

        $api = new api();
        $resource = '/api/events/' . $eventidentifier . '/acl';
        $jsonacl = $api->oc_get($resource);

        $event = new \block_opencast\local\event();
        $event->set_json_acl($jsonacl);

        // Remove acls.
        if ($oldvisibility === block_opencast_renderer::MIXED_VISIBLITY) {
            $oldacls = array();
            array_merge($oldacls, $this->get_non_permanent_acl_rules_for_status($courseid,
                block_opencast_renderer::GROUP, $oldgroupsarray));
            array_merge($oldacls, $this->get_non_permanent_acl_rules_for_status($courseid,
                block_opencast_renderer::VISIBLE, $oldgroupsarray));
        } else {
            $oldacls = $this->get_non_permanent_acl_rules_for_status($courseid, $oldvisibility, $oldgroupsarray);
        }
        foreach ($oldacls as $acl) {
            $event->remove_acl($acl->action, $acl->role);
        }

        // Add new acls.
        $newacls = $this->get_non_permanent_acl_rules_for_status($courseid, $visibility, $groups);
        $newacls = array_merge($newacls, $this->get_permanent_acl_rules_for_status($courseid, $visibility, $groups));
        foreach ($newacls as $acl) {
            $event->add_acl($acl->allow, $acl->action, $acl->role);
        }

        $resource = '/api/events/' . $eventidentifier . '/acl';
        $params['acl'] = $event->get_json_acl();

        // Acl roles have not changed.
        if ($params['acl'] == ($jsonacl)) {
            return 'aclnothingtobesaved';
        }

        $api = new api();

        $api->oc_put($resource, $params);

        if ($api->get_http_code() >= 400) {
            return false;
        }

        // Trigger workflow.
        if ($this->update_metadata($eventidentifier)) {
            switch ($visibility) {
                case block_opencast_renderer::VISIBLE:
                    return 'aclrolesadded';
                case block_opencast_renderer::HIDDEN:
                    return 'aclrolesdeleted';
                case block_opencast_renderer::GROUP:
                    return 'aclrolesaddedgroup';
            }
        }
        return false;

    }

    /**
     * Assign the given series to a course.
     *
     * @param string $eventidentifier
     * @param string $seriesidentifier
     * @return boolean
     */
    public function ensure_series_assigned($eventidentifier, $seriesidentifier)
    {

        $resource = '/api/events/' . $eventidentifier . '/metadata?type=dublincore/episode';

        $params['metadata'] = json_encode(array(array('id' => 'isPartOf', 'value' => $seriesidentifier)));
        $api = new api();
        $api->oc_put($resource, $params);

        return ($api->get_http_code() == 204);
    }

    /**
     * Returns the expected set of non-permanent acl rules for the given status in the context of an event.
     * Can be used for comparision with the actual set of acl rules.
     * @param int $courseid id of the course the event belongs to.
     * @param int $visibility visibility of the event.
     * @param array|null $groups array of group ids used for replacing the placeholders
     * @return array of objects representing acl rules, each with the fields 'allow', 'action' and 'role'.
     * @throws \dml_exception
     * @throws \coding_exception In case of an invalid visibility status. Only [0,1,2] are allowed.
     */
    private function get_non_permanent_acl_rules_for_status($courseid, $visibility, $groups = null)
    {
        return $this->get_acl_rules_for_status($courseid, $visibility, false, $groups);
    }

    /**
     * Returns the expected set of permanent acl rules for the given status in the context of an event.
     * Can be used for comparision with the actual set of acl rules.
     * @param int $courseid id of the course the event belongs to.
     * @param int $visibility visibility of the event.
     * @param array|null $groups array of group ids used for replacing the placeholders
     * @return array of objects representing acl rules, each with the fields 'allow', 'action' and 'role'.
     * @throws \dml_exception
     * @throws \coding_exception In case of an invalid visibility status. Only [0,1,2] are allowed.
     */
    private function get_permanent_acl_rules_for_status($courseid, $visibility, $groups = null)
    {
        return $this->get_acl_rules_for_status($courseid, $visibility, true, $groups);
    }

    /**
     * Returns the expected set of acl rules for the given status in the context of an event.
     * Can be used for comparision with the actual set of acl rules.
     * @param int $courseid id of the course the event belongs to.
     * @param int $visibility visibility of the event.
     * @param bool $permanent whether to get permanent or non-permanent acl rules.
     * @param array|null $groups array of group ids used for replacing the placeholders
     * @return array of objects representing acl rules, each with the fields 'allow', 'action' and 'role'.
     * @throws \dml_exception
     * @throws \coding_exception In case of an invalid visibility status. Only [0,1,2] are allowed.
     */
    private function get_acl_rules_for_status($courseid, $visibility, $permanent, $groups = null)
    {
        $roles = $this->getroles($permanent ? 1 : 0);

        $result = array();

        switch ($visibility) {
            case block_opencast_renderer::VISIBLE:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        $rolenameformatted = $this->replace_placeholders($role->rolename, $courseid)[0];
                        // Might return null if USERNAME cannot be replaced.
                        if ($rolenameformatted) {
                            $result [] = (object)array(
                                'allow' => true,
                                'action' => $action,
                                'role' => $rolenameformatted,
                            );
                        }
                    }
                }
                break;
            case block_opencast_renderer::HIDDEN:
                break;
            case block_opencast_renderer::GROUP:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        foreach ($this->replace_placeholders($role->rolename, $courseid, $groups) as $rule) {
                            if ($rule) {
                                $result [] = (object)array(
                                    'allow' => true,
                                    'action' => $action,
                                    'role' => $rule,
                                );
                            }
                        }
                    }
                }
                break;
            default:
                throw new \coding_exception('The provided visibility status is not valid!');
        }
        return $result;
    }

    /**
     * Checks if momentarily not permanent roles have the necessary actions for a event to be visible.
     *
     * @param string $eventidentifier Event id
     * @param int $courseid Course id
     * @return int state of the visibility (0 hidden, 1 mixed visibility, 2 visible)
     */
    public function is_event_visible($eventidentifier, $courseid)
    {
        $resource = '/api/events/' . $eventidentifier . '/acl';
        $api = new api();
        $jsonacl = $api->oc_get($resource);
        $event = new \block_opencast\local\event();
        $event->set_json_acl($jsonacl);

        $groups = groupaccess::get_record(array('opencasteventid' => $eventidentifier));
        $groupsarray = $groups ? explode(',', $groups->get('groups')) : array();

        $visibleacl = $this->get_non_permanent_acl_rules_for_status($courseid, \block_opencast_renderer::VISIBLE);
        $groupacl = $this->get_non_permanent_acl_rules_for_status($courseid, \block_opencast_renderer::GROUP, $groupsarray);

        $hasallvisibleacls = true;
        $hasnovisibleacls = true;
        $hasaclnotingroup = false;
        foreach ($visibleacl as $acl) {
            if (!$event->has_acl($acl->allow, $acl->action, $acl->role)) {
                $hasallvisibleacls = false;
            } else {
                if (!in_array($acl, $groupacl)) {
                    $hasaclnotingroup = true;
                }
                $hasnovisibleacls = true;
            }
        }
        $hasallgroupacls = true;
        if (!empty($groupsarray)) {
            $hasallgroupacls = true;
            foreach ($groupacl as $acl) {
                if (!$event->has_acl($acl->allow, $acl->action, $acl->role)) {
                    $hasallgroupacls = false;
                }
            }
        }

        $roles = $this->getroles(0);
        $hasnogroupacls = true;
        foreach ($roles as $role) {
            $pattern = $this->get_pattern_for_group_placeholder($role->rolename, $courseid);
            $eventacls = json_decode($jsonacl);
            foreach ($eventacls as $acl) {
                if (preg_match($pattern, $acl->role)) {
                    $hasnogroupacls = false;
                }
            }
        }
        // If all non permanent acls for visibility are set the event is visible.
        if ($hasallvisibleacls) {
            return \block_opencast_renderer::VISIBLE;
        } else if (!empty($groupsarray) && $hasallgroupacls && !$hasaclnotingroup) {
            // If we have groups and the acl rules for each group is present and we do not have non-permanent acls,
            // which do not belong to group visibility, then visibility is group.
            return \block_opencast_renderer::GROUP;
        } else if (empty($groupsarray) && $hasnogroupacls & $hasnovisibleacls) {
            // The visibility is hidden if we have no groupaccess and
            // if there is no acl for group or full visibility in the set.
            return \block_opencast_renderer::HIDDEN;
        } else {
            // In all other cases we have mixed visibility.
            return \block_opencast_renderer::MIXED_VISIBLITY;
        }
    }

    /**
     * Triggers the workflow to update the metadata in opencast.
     * This is necessary, when ACL rules of an event were updated in order to republish the video with the correct
     * access rights.
     * @param string $eventid id of the event the metadata should be updated for.
     * @return bool true, if the workflow was successfully started.
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function update_metadata($eventid)
    {
        $workflow = get_config('block_opencast', 'workflow_roles');
        if (!$workflow) {
            return true;
        }
        return $this->start_workflow($eventid, $workflow);
    }

    /**
     * Starts a workflow in the opencast system.
     * @param string $eventid event id in the opencast system.
     * @param string $workflow identifier of the workflow to be started.
     * @param array $params (optional) The workflow configuration.
     * @param bool $returnworkflowid (optional) Return the workflow ID instead of just a boolean.
     * @return bool|int false if the workflow was not successfully started;
     *                  true or the workflow ID (if $returnworkflowid was set) if the workflow was successfully started.
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function start_workflow($eventid, $workflow, $params = array(), $returnworkflowid = false)
    {
        if (!$workflow) {
            return false;
        }

        // Start workflow.
        $resource = '/api/workflows';
        $params['workflow_definition_identifier'] = $workflow;
        $params['event_identifier'] = $eventid;

        $api = new api();
        $result = $api->oc_post($resource, $params);

        if ($api->get_http_code() != 201) {
            return false;
        }

        // If requested, return the workflow ID now instead of just a boolean at the end of the function.
        if ($returnworkflowid == true) {
            $returnobject = json_decode($result);
            if (isset($returnobject->identifier) && is_number($returnobject->identifier)) {
                return $returnobject->identifier;
            }
        }

        return true;
    }

    /**
     * Checks whether a workflow exists or not.
     *
     * @param string $name id of workflow
     * @return boolean True if workflow exists
     */
    public function check_if_workflow_exists($name)
    {
        $workflows = $this->get_existing_workflows();

        return array_key_exists($name, $workflows);
    }

    /**
     * Retrieves all workflows from the OC system and parses them to be easily processable.
     * @param string $tag if not empty the workflows are filter according to this tag.
     * @param bool $onlynames If only the names of the workflows should be returned
     * @param false $withconfigurations If true, the configurations are included
     * @return array of OC workflows. The keys represent the ID of the workflow,
     * while the value contains its displayname. This is either the description, if set, or the ID. If not $onlynames,
     * the workflows details are also included.
     * @throws \moodle_exception
     */
    public function get_existing_workflows($tag = '', $onlynames = true, $withconfigurations = false)
    {
        $workflows = array();
        $resource = '/api/workflow-definitions';
        $api = new api();
        $resource .= '?filter=tag:' . $tag;

        if ($withconfigurations) {
            $resource .= '&withconfigurationpanel=true';
        }

        $result = $api->oc_get($resource);
        if ($api->get_http_code() === 200) {
            $returnedworkflows = json_decode($result);

            if (!$onlynames) {
                return $returnedworkflows;
            }

            foreach ($returnedworkflows as $workflow) {

                if (object_property_exists($workflow, 'title') && !empty($workflow->title)) {
                    $workflows[$workflow->identifier] = $workflow->title;
                } else {
                    $workflows[$workflow->identifier] = $workflow->identifier;
                }
            }
            return $workflows;
        } else if ($api->get_http_code() == 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }
    }

    /**
     * Retrieves a workflow definition from Opencast.
     * @param string $id Workflow definition id
     * @return false|mixed Workflow definition or false if not successful
     */
    public function get_workflow_definition($id)
    {
        $resource = '/api/workflow-definitions/' . $id;
        $api = new api();
        $resource .= '?withconfigurationpanel=true';

        $result = $api->oc_get($resource);
        if ($api->get_http_code() === 200) {
            return json_decode($result);
        }

        return false;
    }

    /**
     * Helperfunction to get the list of available workflows to be used in the plugin's settings.
     *
     * @param string $tag If not empty the workflows are filtered according to this tag.
     * @param bool $withnoworkflow Add a 'no workflow' item to the list of workflows.
     *
     * @return array Returns array of OC workflows.
     *               If the list of workflows can't be retrieved from Opencast, an array with a nice error message is returned.
     */
    public function get_available_workflows_for_menu($tag = '', $withnoworkflow = false)
    {
        // Get the workflow list.
        $workflows = $this->get_existing_workflows($tag);

        // If requested, add the 'no workflow' item to the list of workflows.
        if ($withnoworkflow == true) {
            $noworkflow = [null => get_string('adminchoice_noworkflow', 'block_opencast')];
            $workflows = array_merge($noworkflow, $workflows);
        }

        // Finally, return the list of workflows.
        return $workflows;
    }

    /**
     * Can delete the event in opencast.
     * @param object $video opencast video.
     * @param int $courseid Course id
     * @return bool True, if event assignment can be deleted
     */
    public function can_delete_event_assignment($video, $courseid)
    {

        if (isset($video->processing_state) &&
            ($video->processing_state !== 'RUNNING' && $video->processing_state !== 'PAUSED')) {

            $context = \context_course::instance($courseid);

            return has_capability('block/opencast:deleteevent', $context);
        }

        return false;
    }

    /**
     * Triggers the deletion of an event. Dependent on the settings a deletion workflow is started in advance.
     *
     * @param string $eventidentifier
     * @return boolean return true when video deletion is triggerd correctly.
     */
    public function trigger_delete_event($eventidentifier)
    {
        global $DB;
        $workflow = get_config("block_opencast", "deleteworkflow");
        if ($workflow) {
            $this->start_workflow($eventidentifier, $workflow);

            $record = [
                "opencasteventid" => $eventidentifier,
                "failed" => false,
                "timecreated" => time(),
                "timemodified" => time()
            ];
            $DB->insert_record("block_opencast_deletejob", $record);
        } else {
            $this->delete_event($eventidentifier);
        }
        return true;
    }

    /**
     * Delete an event. Verify the video and check capability before.
     *
     * @param string $eventidentifier
     * @return boolean return true when video is deleted.
     */
    public function delete_event($eventidentifier)
    {

        $resource = '/api/events/' . $eventidentifier;

        $api = new api();
        $api->oc_delete($resource);

        if ($api->get_http_code() != 204) {
            return false;
        }
        return true;
    }

    /**
     * Get course videos for backup. This might retrieve only the videos, that
     * have a processing state of SUCCEDED.
     *
     * @param int $courseid
     * @param array $processingstates
     *
     * @return array list of videos for backup.
     */
    public function get_course_videos_for_backup($courseid, $processingstates = ['SUCCEEDED'])
    {

        if (!$result = $this->get_course_videos($courseid)) {
            return [];
        }

        if ($result->error != 0) {
            return [];
        }

        $videosforbackup = [];
        foreach ($result->videos as $video) {
            if (in_array($video->processing_state, $processingstates)) {
                $videosforbackup[$video->identifier] = $video;
            }
        }

        return $videosforbackup;
    }

    /**
     * Check, whether the opencast system supports a given level.
     *
     * @param string $level
     * @return boolean
     */
    public function supports_api_level($level)
    {

        $api = new api();
        try {
            return $api->supports_api_level($level);
        } catch (\moodle_exception $e) {
            debugging('Api level ' . $level . ' not supported.');
            return false;
        }
        return false;
    }

    /**
     * If testing is set to true and we are in PHP_UNIT environment, a new instance of the apibridge will result in
     * a testable class. It also resets the current apibridge instance.
     * @param bool $testing true, if get_instance should return a testable.
     */
    public static function set_testing($testing)
    {
        self::$testing = $testing;
        self::get_instance(true);
    }

    // Metadata.

    /**
     * The allowance of the update metadata process
     * @param object $video Opencast video
     * @param int $courseid Course id
     * @return bool the capability of updating!
     */
    public function can_update_event_metadata($video, $courseid)
    {

        if (isset($video->processing_state) &&
            ($video->processing_state == "SUCCEEDED" || $video->processing_state == "FAILED" ||
                $video->processing_state == "PLANNED" || $video->processing_state == "STOPPED")) {
            $context = \context_course::instance($courseid);
            return has_capability('block/opencast:addvideo', $context);
        }

        return false;
    }

    /**
     * Get the event's metadata of the specified type
     * @param string $eventidentifier Event id
     * @param string $query Api query additions
     * @return bool|int|mixed Event metadata
     */
    public function get_event_metadata($eventidentifier, $query = '')
    {
        $api = new api();
        $resource = '/api/events/' . $eventidentifier . '/metadata' . $query;
        $metadata = $api->oc_get($resource);

        if ($api->get_http_code() != 200) {
            return $api->get_http_code();
        }

        return json_decode($metadata);

    }

    /**
     * Update the metadata with the matching type of the specified event.
     * @param string $eventidentifier identifier of the event
     * @param stdClass $metadata collection of metadata
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update_event_metadata($eventidentifier, $metadata)
    {
        $resource = '/api/events/' . $eventidentifier . '/metadata?type=dublincore/episode';

        $params['metadata'] = json_encode($metadata);
        $api = new api();
        $api->oc_put($resource, $params);

        if ($api->get_http_code() == 204) {
            $video = $this->get_opencast_video($eventidentifier);

            if ($video->error === 0) {
                // Don't start workflow for scheduled videos
                if ($video->video->processing_state !== "PLANNED") {
                    return $this->update_metadata($eventidentifier);
                }
                return true;
            }
            return false;

        };
        return false;
    }

    /**
     * Update the metadata with the matching type of the specified series.
     * @param string $eventidentifier identifier of the series
     * @param stdClass $metadata collection of metadata
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update_series_metadata($seriesid, $metadata) {
        $resource = '/api/series/' . $seriesid . '/metadata?type=dublincore/series';

        $params['metadata'] = json_encode($metadata);
        $api = new api();
        $api->oc_put($resource, $params);

        if ($api->get_http_code() == 204) {
            // TODO do I need to trigger any workflow for this?
           //  return $this->update_metadata($eventidentifier);
            return true;
        };
        return false;
    }


    /**
     * Get the episode id of the episode which was created in a duplication workflow.
     *
     * @param int $workflowid The workflow ID of the dupliation workflow.
     *
     * @return string|bool The episode ID, if an episode ID was found.
     *                     An empty string, if the workflow does not contain an episode ID yet.
     *                     False, if the workflow does not exist at all,
     *                         if we don't look at an duplication workflow at all,
     *                         if the found episode ID isn't a valid episode ID at all,
     *                         if the workflow has ended but there still isn't an episode ID.
     */
    public function get_duplicated_episodeid($workflowid)
    {

        // If we don't have a number, return.
        if (!is_number($workflowid)) {
            return false;
        }

        // Get API.
        $api = new api();

        // Build API request.
        $resource = '/api/workflows/' . $workflowid . '?withconfiguration=true';

        // Run API request.
        $result = $api->oc_get($resource);

        // If the given workflow was not found, return.
        if ($api->get_http_code() != 200) {
            return false;
        }

        // Decode the result, return if the decoding fails.
        if (!$workflowconfiguration = json_decode($result)) {
            return false;
        }

        // If we are not looking at a duplication workflow at all, return.
        $duplicateworkflow = get_config('block_opencast', 'duplicateworkflow');
        if (isset($workflowconfiguration->workflow_definition_identifier) &&
            $workflowconfiguration->workflow_definition_identifier != $duplicateworkflow) {
            return false;
        }

        // If the workflow is not running anymore and there is no chance that there will be a (valid) episode ID anymore, return.
        if (isset($workflowconfiguration->state) &&
            !($workflowconfiguration->state == 'instantiated' || $workflowconfiguration->state == 'running' ||
                $workflowconfiguration->state == 'paused') &&
            (!isset($workflowconfiguration->configuration->duplicate_media_package_1_id) ||
                empty($workflowconfiguration->configuration->duplicate_media_package_1_id) ||
                ltimodulemanager::is_valid_episode_id(
                    $workflowconfiguration->configuration->duplicate_media_package_1_id) == false)) {
            return false;
        }

        // Now, regardless if the workflow has finished already or not, check if there is already a valid episode ID.
        if (isset($workflowconfiguration->configuration->duplicate_media_package_1_id) &&
            ltimodulemanager::is_valid_episode_id($workflowconfiguration->configuration->duplicate_media_package_1_id) == true) {
            // Pick the episode ID from the workflow configuration and return it.
            return $workflowconfiguration->configuration->duplicate_media_package_1_id;
        }

        // In all other cases, return an empty string to let the caller try again later.
        return '';
    }
}
