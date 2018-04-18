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

use core_availability\result;
use tool_opencast\seriesmapping;
use tool_opencast\local\api;
use block_opencast\opencast_state_exception;

require_once($CFG->dirroot . '/lib/filelib.php');

class apibridge {

    private $config;

    private function __construct() {

        $this->config = get_config('block_opencast');
    }

    /**
     * Get an instance of an object of this class. Create as a singleton.
     *
     * @staticvar report_helper $apibridge
     *
     * @param boolean $forcenewinstance true, when a new instance should be created.
     *
     * @return apibridge
     */
    public static function get_instance($forcenewinstance = false) {
        static $apibridge;

        if (isset($apibridge) && !$forcenewinstance) {
            return $apibridge;
        }

        $apibridge = new apibridge();

        return $apibridge;
    }

    /**
     * Get videos to show in block. Items are limited and ready to use by renderer.
     * Note that we try to receive one item more than configurated to decide whether
     * to display a "more videos" link.
     *
     * @param int $courseid
     *
     * @return \stdClass
     */
    public function get_block_videos($courseid) {

        $result = new \stdClass();
        $result->count = 0;
        $result->more = false;
        $result->videos = array();
        $result->error = 0;

        $mapping = seriesmapping::get_record(array('courseid' => $courseid));

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
//        $withroles[] = api::get_course_acl_role($courseid);

        $api = new api();

        $videos = $api->oc_get($url, $withroles);

        if ($api->get_http_code() != 200) {
            $result->error = $api->get_http_code();

            return $result;
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
        $result->videos = $videos;

        return $result;
    }

    /**
     * Get all the videos (events) for a course.
     * Note that they are restricted by course role.
     *
     * @param int             $courseid
     * @param \flexible_table $table
     * @param int             $perpage
     * @param boolean         $download
     *
     * @return array
     */
    public function get_course_videos($courseid, $table, $perpage, $download) {

        $sortcolums = $table->get_sort_columns();
        $sort = api::get_sort_param($sortcolums);

        $result = new \stdClass();
        $result->videos = array();
        $result->error = 0;

        $series = $this->get_course_series($courseid);

        if (!isset($series)) {
            return $result;
        }
        $seriesfilter = "series:" . $series->identifier;

        $query = 'sign=1&withacl=1&withmetadata=1&withpublications=1&filter=' . urlencode($seriesfilter) . $sort;

        $resource = '/api/events?' . $query;

        $withroles = array();
//        $withroles[] = api::get_course_acl_role($courseid);

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

        return $result;
    }

    public function get_opencast_video($courseid, $identifier) {

        $query = 'sign=1&withacl=1&withmetadata=1&withpublications=1';
        $resource = '/api/events/' . $identifier;

        $withroles = array();
//        $withroles[] = api::get_course_acl_role($courseid);

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

        $result->video = $video;

        return $result;
    }

    /**
     * API call to check, whether the course related group exists in opencast system.
     *
     * @param int $courseid
     *
     * @return object group object of NULL, if group does not exist.
     */
    protected function get_acl_group($courseid) {

        $groupname = $this->replace_placeholders(get_config('block_opencast', 'group_name'), $courseid);
        $groupidentifier = $this->get_course_acl_group_identifier($groupname);

        $api = new api();
        $group = $api->oc_get('/api/groups/' . $groupidentifier);

        return json_decode($group);
    }

    /**
     * Returns the group identifier from a group name.
     *
     * @param String $groupname
     *
     * @return mixed
     */
    private function get_course_acl_group_identifier($groupname) {
        $groupidentifier = mb_strtolower($groupname, 'UTF-8');

        return preg_replace('/[^a-zA-Z0-9_]/', '_', $groupidentifier);
    }

    /**
     * API call to create a group for given course.
     *
     * @param int $courseid
     *
     * @return object group object of NULL, if group does not exist.
     */
    protected function create_acl_group($courseid) {


        $params = [];
        $params['name'] = $this->replace_placeholders(get_config('block_opencast', 'group_name'), $courseid);
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
     *
     * @return object group object.
     * @throws opencast_state_exception
     */
    public function ensure_acl_group_exists($courseid) {

        $group = $this->get_acl_group($courseid);

        if (!isset($group->identifier)) {
            $this->create_acl_group($courseid);
            // Check success.
            $group = $this->get_acl_group($courseid);
        }

        if (!isset($group->identifier)) {
            throw new opencast_state_exception('missinggroup', 'block_opencast');
        }

        return $group;
    }

    /**
     * API call to check, whether the course related series exists in opencast system.
     *
     * @param int $courseid
     *
     * @return object group object of NULL, if group does not exist.
     */
    public function get_course_series($courseid) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid));

        if (!$mapping || !($seriesid = $mapping->get('series'))) {
            return null;
        }

        $url = '/api/series/' . $seriesid;

        $api = new api();

        $series = $api->oc_get($url);

        return json_decode($series);
    }

    /**
     * Replaces the placeholders [COURSENAME] and [COURSEID]
     *
     * @param string $seriesname
     * @param int    $courseid
     *
     * @return mixed
     */
    private function replace_placeholders($name, $courseid) {
        $coursename = get_course($courseid)->fullname;
        $title = str_replace('[COURSENAME]', $coursename, $name);

        return str_replace('[COURSEID]', $courseid, $title);
    }

    /**
     * API call to create a series for given course.
     *
     * @param int $courseid
     */
    public function create_course_series($courseid, $seriestitle = null) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid));
        if ($mapping && $seriesid = $mapping->get('series')) {
            throw new \moodle_exception(get_string('series_exists', 'block_opencast', $seriesid));
        }

        $params = [];

        $metadata = array();
        $metadata['label'] = "Opencast Series Dublincore";
        $metadata['flavor'] = "dublincore/series";
        $metadata['fields'] = [];

        if (is_null($seriestitle)) {
            $title = get_config('block_opencast', 'series_name');
        } else {
            $title = $seriestitle;
        }

        $metadata['fields'][] = array('id' => 'title', 'value' => $this->replace_placeholders($title, $courseid));

        $params['metadata'] = json_encode(array($metadata));

        $acl = array();
        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $acl[] = (object) array('allow' => true, 'action' => $action, 'role' => $this->replace_placeholders($role->rolename, $courseid));
            }
        }

        $params['acl'] = json_encode(array_values($acl));
        $params['theme'] = '';

        $api = new api();

        $result = $api->oc_post('/api/series', $params);

        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }

        $series = json_decode($result);
        if (isset($series) && object_property_exists($series, 'identifier')) {
            $mapping = new seriesmapping();
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $series->identifier);
            $mapping->create();
        }
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
    public function ensure_course_series_exists($courseid) {

        $series = $this->get_course_series($courseid);

        if (!isset($series)) {
            $this->create_course_series($courseid);
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
     * @param $courseid Course ID
     * @param $seriesid Series ID
     */
    public function update_course_series($courseid, $seriesid) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid));
        $mapping->set('series', $seriesid);
        $mapping->update();

        // Update Acl roles
        $api = new api();
        $resource = '/api/series/' . $seriesid . '/acl';
        $jsonacl = $api->oc_get($resource);

        $acl = json_decode($jsonacl);

        if (!is_array($acl)) {
            throw new \moodle_exception('invalidacldata', 'block_opencast');
        }

        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {

                foreach ($acl as $key => $aclval) {
                    if (($aclval->action == $action) && ($aclval->role == $role)) {
                        unset($acl[$key]);
                    }
                }

                $acl[] = (object) array('allow' => true, 'role' => $this->replace_placeholders($role->rolename, $courseid), 'action' => $action);
            }
        }

        $params['acl'] =  json_encode(array_values($acl));

        // Acl roles have not changed
        if ($params['acl'] == ($jsonacl)) {
            return true;
        }

        $api = new api();

        $api->oc_put($resource, $params);

        return ($api->get_http_code() == 204);
    }

    public function ensure_series_is_valid($seriesid) {
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
    public function get_already_existing_event($opencastids) {

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
     *
     * @return object series object of NULL, if group does not exist.
     */
    public function create_event($job, $seriesidentifier) {
        $event = new \block_opencast\local\event();

        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $event->add_acl(true, $action, $this->replace_placeholders($role->rolename, $job->courseid));
            }
        }

        $event->set_presentation($job->fileid);
        $storedfile = $event->get_presentation();

        $event->add_meta_data('title', $storedfile->get_filename());
        $event->add_meta_data('isPartOf', $seriesidentifier);
        $params = $event->get_form_params();

        $api = new api();

        $result = $api->oc_post('/api/events', $params);

        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }

        return $result;
    }

    /**
     * Returns an array of acl roles. The actions field of each entry contains an array of trimmed action names
     * for the specific role.
     * @return array of acl roles.
     * @throws \dml_exception A DML specific exception is thrown for any errors.
     */
    protected function getroles() {
        global $DB;
        $roles = $DB->get_records('block_opencast_roles');
        foreach ($roles as $id => $role) {
            $actions = explode(',', $role->actions);
            $roles[$id]->actions = array();
            foreach ($actions as $action) {
                $roles[$id]->actions [] = trim($action);
            }
        }

        return $roles;
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
    public function ensure_event_exists($job, $opencastids, $seriesidentifier) {

        if ($opencastids) {
            if ($event = $this->get_already_existing_event($opencastids)) {
                // Flag as existing event.
                $event->newlycreated = false;

                return $event;
            }
        }

        $event = $this->create_event($job, $seriesidentifier);
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
     * @param int    $courseid
     *
     * @return boolean true if succeeded
     */
    public function ensure_acl_group_assigned($eventidentifier, $courseid) {

        $api = new api();
        $resource = '/api/events/' . $eventidentifier . '/acl';
        $jsonacl = $api->oc_get($resource);

        $event = new \block_opencast\local\event();
        $event->set_json_acl($jsonacl);

        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $event->add_acl(true, $action, $this->replace_placeholders($role->rolename, $courseid));
            }
        }

        $resource = '/api/events/' . $eventidentifier . '/acl';
        $params['acl'] = $event->get_json_acl();

        // Acl roles have not changed
        if ($params['acl'] == ($jsonacl)) {
            return true;
        }

        $api = new api();

        $api->oc_put($resource, $params);

        return ($api->get_http_code() == 204);
    }

    /**
     * Can delete the acl group assignment.
     *
     * @param object $video opencast video.
     */
    public function can_delete_acl_group_assignment($video) {

        return (isset($video->processing_state) && ($video->processing_state == 'SUCCEEDED'));
    }

    /**
     * Remove the group role assignment for the event.
     *
     * @param string $eventidentifier
     * @param int    $courseid
     *
     * @return boolean true if succeeded
     */
    public function delete_acl_group_assigned($eventidentifier, $courseid) {
        $resource = '/api/events/' . $eventidentifier . '/acl';
        $api = new api();
        $jsonacl = $api->oc_get($resource);

        $event = new \block_opencast\local\event();
        $event->set_json_acl($jsonacl);

        $grouprole = api::get_course_acl_role($courseid);
        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $event->add_acl(true, $action, $this->replace_placeholders($role->rolename, $courseid));
            }
        }
        $event->remove_acl('read', $grouprole);

        $resource = '/api/events/' . $eventidentifier . '/acl';
        $params['acl'] = $event->get_json_acl();

        $api = new api();
        $api->oc_put($resource, $params);

        if ($api->get_http_code() != 204) {
            return false;
        }

        // Adapt course series.
        if (!$courseid = $event->get_next_series_courseid()) {
            $this->ensure_series_assigned($eventidentifier, '');
        }

        $series = $this->ensure_course_series_exists($courseid);

        return $this->ensure_series_assigned($eventidentifier, $series->identifier);
    }

    /**
     * Assign the given series to a course.
     *
     * @param string $eventidentifier
     * @param string $seriesidentifier
     *
     * @return boolean
     */
    public function ensure_series_assigned($eventidentifier, $seriesidentifier) {

        $resource = '/api/events/' . $eventidentifier . '/metadata?type=dublincore/episode';

        $params['metadata'] = json_encode(array(array('id' => 'isPartOf', 'value' => $seriesidentifier)));
        $api = new api();
        $api->oc_put($resource, $params);

        return ($api->get_http_code() == 204);
    }

}
