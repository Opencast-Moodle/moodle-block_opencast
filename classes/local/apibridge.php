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
use block_opencast\local\upload_helper;
use block_opencast_renderer;
use tool_opencast\seriesmapping;
use tool_opencast\local\api;
use block_opencast\opencast_state_exception;

require_once($CFG->dirroot . '/lib/filelib.php');
require_once(__DIR__ . '/../../renderer.php');
require_once($CFG->dirroot.'/blocks/opencast/tests/helper/apibridge_testable.php');

class apibridge {

    private $config;

    private $workflows = array();

    private static $testing = false;

    private function __construct() {

        $this->config = get_config('block_opencast');
    }

    /**
     * Get an instance of an object of this class. Create as a singleton.
     *
     * @staticvar \apibridge $apibridge
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

        // Use replacement of api bridge for test cases.
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && self::$testing) {
            $apibridge = new \block_opencast_apibridge_testable();
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
     *
     * @return array
     */
    public function get_course_videos($courseid, $sortcolumns = null) {

        $result = new \stdClass();
        $result->videos = array();
        $result->error = 0;

        $series = $this->get_course_series($courseid);

        if (!isset($series)) {
            return $result;
        }
        $seriesfilter = "series:" . $series->identifier;

        $query = 'sign=1&withacl=1&withmetadata=1withpublications=1&filter=' . urlencode($seriesfilter);
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
     * @param $video [] The video object, which should be checked.
     */
    private function extend_video_status(&$video) {
        $api = new api();
        $plannedvideo = null;
        if (!$api->supports_api_level('v1.1.0')) {
            $resourceopencast5 = '/recordings/' . $video->identifier . '/technical.json';
            $api = new api();
            $plannedvideo = json_decode($api->oc_get($resourceopencast5));

            if ($api->get_http_code() === 200 && $plannedvideo->state === "") {
                $video->processing_state = "PLANNED";
            }
        } else {
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
                }
        }

    }

    public function get_opencast_video($identifier) {

        $resource = '/api/events/' . $identifier;

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

        $groupname = $this->replace_placeholders(get_config('block_opencast', 'group_name'), $courseid)[0];
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
        $params['name'] = $this->replace_placeholders(get_config('block_opencast', 'group_name'), $courseid)[0];
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
     * Persist the new groups for the eventid;
     * @param string $eventid id of the event
     * @param int[] $groups ids of all groups for which access should be provided.
     * If $groups is empty the access is not restricted.
     * @return bool
     */
    private function store_group_access($eventid, $groups) {
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
        } catch (\moodle_exception $e){
            return false;
        }
        return true;
    }

    /**
     * Retrieves the id of the series, which is stored in the admin tool.
     *
     * @param int $courseid id of the course.
     *
     * @return string id of the series
     */
    public function get_stored_seriesid($courseid) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid));

        if (!$mapping || !($seriesid = $mapping->get('series'))) {
            return null;
        }

        return $seriesid;
    }

    /**
     * API call to check, whether series exists in opencast system.
     *
     * @param int $seriesid
     *
     * @return null|string id of the series id if it exists in the opencast system.
     */
    public function get_series_by_identifier($seriesid) {

        $url = '/api/series/' . $seriesid;

        $api = new api();

        $series = $api->oc_get($url);

        return json_decode($series);
    }

    /**
     * API call to check, whether the course related series exists in opencast system.
     *
     * @param int $courseid
     *
     * @return null|string id of the series id if it exists in the opencast system.
     */
    public function get_course_series($courseid) {

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
    private function replace_placeholders($name, $courseid, $groups = null) {
        $coursename = get_course($courseid)->fullname;
        $title = str_replace('[COURSENAME]', $coursename, $name);
        $title = str_replace('[COURSEID]', $courseid, $title);

        $result = array();

        if (strpos($name, '[COURSEGROUPID]') >= 0) {
            if ($groups) {
                foreach ($groups as $groupid) {
                    $result [] = str_replace('[COURSEGROUPID]', 'G' . $groupid, $title);
                }
            } else {
                $result [] = str_replace('[COURSEGROUPID]', $courseid, $title);
            }
        } else {
            $result []= $title;
        }

        return $result;
    }

    /**
     * The function returns a needle for a search among a set of acl. The goal is to check,
     * if there are any group related acl rules.
     * @param $name
     * @param $courseid
     * @throws \dml_exception
     */
    private function get_pattern_for_group_placeholder($name, $courseid) {
        $coursename = get_course($courseid)->fullname;
        $title = str_replace('[COURSENAME]', $coursename, $name);
        $title = str_replace('[COURSEID]', $courseid, $title);
        return '/' . str_replace('[COURSEGROUPID]', 'G\\d*', $title) . '/' ;
    }

    /**
     * Returns the default series name for a course.
     * @param $courseid int id of the course.
     * @return string default series title.
     * @throws \dml_exception
     */
    public function get_default_seriestitle($courseid) {
        $title = get_config('block_opencast', 'series_name');
        return $this->replace_placeholders($title, $courseid)[0];
    }

    /**
     * API call to create a series for given course.
     *
     * @param int $courseid
     * @return bool tells if the creation of the series was successful.
     */
    public function create_course_series($courseid, $seriestitle = null) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid));
        if ($mapping && $seriesid = $mapping->get('series')) {
            throw new \moodle_exception(get_string('series_already_exists', 'block_opencast', $seriesid));
        }

        $params = [];

        $metadata = array();
        $metadata['label'] = "Opencast Series Dublincore";
        $metadata['flavor'] = "dublincore/series";
        $metadata['fields'] = [];

        if (is_null($seriestitle)) {
            $title = $this->get_default_seriestitle($courseid);
        } else {
            $title = $seriestitle;
        }

        $metadata['fields'][] = array('id' => 'title', 'value' => $title);

        $params['metadata'] = json_encode(array($metadata));

        $acl = array();
        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $acl[] = (object) array('allow' => true, 'action' => $action,
                                        'role' => $this->replace_placeholders($role->rolename, $courseid)[0]);
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
            $mapping->create();
            return true;
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

        if (!$mapping) {
            $mapping = new seriesmapping();
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $seriesid);
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
            foreach ($role->actions as $action) {

                foreach ($acl as $key => $aclval) {
                    if (($aclval->action == $action) && ($aclval->role == $role)) {
                        unset($acl[$key]);
                    }
                }

                $acl[] = (object) array('allow' => true,
                                        'role' => $this->replace_placeholders($role->rolename, $courseid)[0],
                                        'action' => $action);
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
     * @param $courseid Course ID
     */
    public function unset_course_series($courseid) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid));

        if ($mapping) {
            $mapping->delete();
        }
    }

    /**
     * Checks if the series ID exists in the Opencast system.
     * @param $seriesid
     * @return bool true, if the series exists. Otherwise false.
     * @throws \dml_exception
     * @throws \moodle_exception if there is no connection to the server.
     */
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

    private function get_ingest_endpoint_path() {
        $api = new api();
        static $path = null;
        if ($path) {
            return $path;
        }
        $response = $api->oc_get('/services/available.json?serviceType=org.opencastproject.ingest');
        $responseobj = json_decode($response);
        // TODO error handling?
        $service = $responseobj->services->service;
        $path = $service->path;
        return $path;
    }

    private function generate_mediapackage_metadata_xml($job) {
        $metadata = json_decode($job->metadata);
        $result = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<dublincore xmlns="http://www.opencastproject.org/xsd/1.0/dublincore/"
    xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $startdate = null;
        $starttime = null;
        foreach ($metadata as $field) {
            // Start date/time uses another format. It is added further below.
            // Attachments aren't part of the metadata catalog. They are uploaded separately.
            if ($field->id == 'startDate') {
                $startdate = $field->value;
                continue;
            } else if ($field->id == 'startTime') {
                $starttime = $field->value;
                continue;
            } else {
                $result .= "\n";
                $result .= '    <dcterms:'.$field->id.'>'.$field->value.'</dcterms:'.$field->id.'>';
            }
        }
        $result .= "\n";
        $start = $startdate.'T'.$starttime;
        $result .= '    <dcterms:created xsi:type="dcterms:W3CDTF">'.$start.'</dcterms:created>';
        $result .= "\n";
        $result .= '    <dcterms:temporal xsi:type="dcterms:Period">start='.$start.'; end='.$start.'; scheme=W3C-DTF;</dcterms:temporal>';
        $seriesid = $this->get_stored_seriesid($job->courseid);
        $result .= "\n";
        $result .= '    <dcterms:isPartOf>'.$seriesid.'</dcterms:isPartOf>';
        $result .= "\n";
        $result .= '</dublincore>';
        return $result;
    }

    private function generate_mediapackage_acl_xml($job) {
        $aclxml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Policy PolicyId="'.$job->opencasteventid.'" Version="2.0" RuleCombiningAlgId="urn:oasis:names:tc:xacml:1.0:rule-combining-algorithm:permit-overrides" xmlns="urn:oasis:names:tc:xacml:2.0:policy:schema:os">
    <Target>
        <Resources>
            <Resource>
                <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
                    <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">'.$job->opencasteventid.'</AttributeValue>
                    <ResourceAttributeDesignator AttributeId="urn:oasis:names:tc:xacml:1.0:resource:resource-id" DataType="http://www.w3.org/2001/XMLSchema#string"/>
                </ResourceMatch>
            </Resource>
        </Resources>
    </Target>';
        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                foreach ($this->replace_placeholders($role->rolename, $job->courseid) as $acl) {
                    $aclxml .= "\n";
                    $aclxml .= '    <Rule RuleId="'.$acl.'_'.$action.'_Permit" Effect="Permit">
        <Target>
            <Actions>
                <Action>
                    <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
                        <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">'.$action.'</AttributeValue>
                        <ActionAttributeDesignator AttributeId="urn:oasis:names:tc:xacml:1.0:action:action-id" DataType="http://www.w3.org/2001/XMLSchema#string"/>
                    </ActionMatch>
                </Action>
            </Actions>
        </Target>
        <Condition>
            <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-is-in">
                <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">'.$acl.'</AttributeValue>
                <SubjectAttributeDesignator AttributeId="urn:oasis:names:tc:xacml:2.0:subject:role" DataType="http://www.w3.org/2001/XMLSchema#string"/>
            </Apply>
        </Condition>
    </Rule>';
                }
            }
        }
        $aclxml .= "\n";
        $aclxml .= '    <Rule RuleId="DenyRule" Effect="Deny"/>
</Policy>';
        return $aclxml;
    }

    private function media_package_upload_track($mediapackagexml, $fileid, $flavor) {
        $ingestpath = $this->get_ingest_endpoint_path();

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($fileid);

        $api = new api();
        return $api->oc_post($ingestpath.'/addTrack', [
            "flavor" => $flavor,
            "mediaPackage" => $mediapackagexml,
            "BODY" => $file
        ]);
    }

    /**
     * API call to create an empty media package.
     * 
     * @return string media package id which becomes event id upon ingestion
     */
    public function create_media_package() {
        $ingestpath = $this->get_ingest_endpoint_path();

        $api = new api();
        $response = $api->oc_get($ingestpath.'/createMediaPackage');
        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }
        return $response;
    }

    public function media_package_upload_metadata($job) {
        $ingestpath = $this->get_ingest_endpoint_path();

        $metadataxml = $this->generate_mediapackage_metadata_xml($job);

        $api = new api();
        $response = $api->oc_post($ingestpath.'/addDCCatalog', [
            'flavor' => 'dublincore/episode',
            'mediaPackage' => $job->mediapackagexml,
            'dublinCore' => $metadataxml
        ]);
        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }
        return $response;
    }

    public function media_package_upload_acl($job) {
        $ingestpath = $this->get_ingest_endpoint_path();

        $aclxml = $this->generate_mediapackage_acl_xml($job);

        // Create temporary XML file with ACL.
        $fs = get_file_storage();
        $ctxid = \context_course::instance($job->courseid)->id;
        $fileinfo = [
            'contextid' => $ctxid,
            'component' => 'block_opencast',
            'filearea' => 'tmp_acl',
            'itemid' => $job->id,
            'filepath' => '/',
            'filename' => 'xacml.xml'];
        $acltmpfile = $fs->create_file_from_string($fileinfo, $aclxml);

        try {
            $api = new api();
            $response = $api->oc_post($ingestpath.'/addAttachment', [
                "flavor" => "security/xacml+episode",
                "mediaPackage" => $job->mediapackagexml,
                "BODY" => $acltmpfile
            ]);
            if ($api->get_http_code() >= 400) {
                throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
            }
            return $response;
        } finally {
            $fs->delete_area_files($ctxid, 'block_opencast', 'tmp_acl', $job->id);
        }
    }

    public function media_package_upload_presenter($job) {
        $response = $this->media_package_upload_track($job->mediapackagexml, $job->presenter_fileid, 'presenter/source');
        $responseobj = simplexml_load_string($response);
        if ($responseobj === false) {
            throw new opencast_state_exception('uploadingvideofailed', 'block_opencast');
        }
        return $response;
    }

    public function media_package_upload_presentation($job) {
        $response = $this->media_package_upload_track($job->mediapackagexml, $job->presentation_fileid, 'presentation/source');
        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }
        return $response;
    }

    public function media_package_upload_attachments($job) {
        $ingestpath = $this->get_ingest_endpoint_path();

        if(empty($job->attachments)) {
            return $job->mediapackagexml;
        }

        $fs = get_file_storage();
        $api = new api();
        $mediapackagexml = $job->mediapackagexml;
        foreach ($job->attachments as $attachment) {
            $file = $fs->get_file_by_id($attachment->fileid);
            if ($file === false) {
                throw new \moodle_exception('attachmentmissingfile', 'block_opencast');
            }
            $flavor = $attachment->flavor_type.'/'.$attachment->flavor_subtype;
    
            $response = $api->oc_post($ingestpath.'/addAttachment', [
                "flavor" => $flavor,
                "mediaPackage" => $mediapackagexml,
                "BODY" => $file
            ]);
            if ($api->get_http_code() >= 400) {
                throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
            }
            $mediapackagexml = $response;
        }

        return $response;
    }

    public function media_package_ingest($job) {
        $ingestpath = $this->get_ingest_endpoint_path();

        $uploadworkflow = get_config('block_opencast', 'uploadworkflow');
        if (empty($uploadworkflow)) {
            $uploadworkflow = 'ng-schedule-and-upload';
        }

        $api = new api();
        $response = $api->oc_post($ingestpath.'/ingest/'.$uploadworkflow, [
            "mediaPackage" => $job->mediapackagexml,
        ]);
        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }
        return $response;
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
    public function getroles($conditions = null) {
        global $DB;
        $roles = $DB->get_records('block_opencast_roles', $conditions);
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
                foreach ($this->replace_placeholders($role->rolename, $courseid, $eventidentifier) as $acl) {
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
     *
     * @param object $video opencast video.
     */
    public function can_delete_acl_group_assignment($video, $courseid) {

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
                foreach ($this->replace_placeholders($role->rolename, $courseid, $eventidentifier) as $acl) {
                    $event->add_acl(true, $action, $acl);
                }
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
     * @param $eventidentifier
     * @param $courseid
     * @param $visibility
     * @param array|null $groups
     * @return string identifier of the notification string to be presented to the user.
     */
    public function change_visibility($eventidentifier, $courseid, $visibility, $groups = null) {
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
                case block_opencast_renderer::VISIBLE: return 'aclrolesadded';
                case block_opencast_renderer::HIDDEN: return 'aclrolesdeleted';
                case block_opencast_renderer::GROUP: return 'aclrolesaddedgroup';
            }
        }
        return false;

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
    private function get_non_permanent_acl_rules_for_status($courseid, $visibility, $groups = null) {
        $roles = $this->getroles(array('permanent' => 0));

        $result = array();

        switch ($visibility) {
            case block_opencast_renderer::VISIBLE:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        $result [] = (object) array(
                            'allow' => true,
                            'action' => $action,
                            'role' => $this->replace_placeholders($role->rolename, $courseid)[0],
                        );
                    }
                }
                break;
            case block_opencast_renderer::HIDDEN:
                break;
            case block_opencast_renderer::GROUP:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        foreach ($this->replace_placeholders($role->rolename, $courseid, $groups) as $rule)
                            $result [] = (object) array(
                                'allow' => true,
                                'action' => $action,
                                'role' => $rule,
                            );
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
     * @param $eventidentifier
     * @param $courseid
     * @return int state of the visibility (0 hidden, 1 mixed visibility, 2 visible)
     */
    public function is_event_visible($eventidentifier, $courseid) {
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
                if (!in_array($acl, $groupacl)){
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

        $roles = $this->getroles(array('permanent' => 0));
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
    private function update_metadata($eventid) {
        $workflow = get_config('block_opencast', 'workflow_roles');
        if (!$workflow) {
            return true;
        }
        return $this->start_workflow($eventid, $workflow);
    }

    public function add_attachment($eventid, $fileid, $attachmentfield) {
        if (empty($attachmentfield->flavor_type) || empty($attachmentfield->flavor_subtype)) {
            throw new \moodle_exception('attachmentmissingflavor', 'tool_opencast');
        }

        // Make sure workflow is even configured before proceeding.
        $workflow = get_config('block_opencast', 'workflow_attachments');
        if ($workflow == "") {
            throw new \moodle_exception('workflownotdefined:attachments', 'block_opencast');
        }

        $metadata = new \stdClass();
        $metadata->assets = new \stdClass();
        $metadata->assets->options = [];
        $metadata->assets->options[] = new \stdClass();
        $metadata->assets->options[0]->id = $attachmentfield->asset_id;
        $metadata->assets->options[0]->type = $attachmentfield->type;
        $metadata->assets->options[0]->flavorType = $attachmentfield->flavor_type;
        $metadata->assets->options[0]->flavorSubType = $attachmentfield->flavor_subtype;
        $metadata->assets->options[0]->displayOrder = 1;
        $metadata->assets->options[0]->title = 'unused';
        $metadata->processing = new \stdClass();
        $metadata->processing->workflow = $workflow;
        $metadata->processing->configuration = new \stdClass();
        $metadata->processing->configuration->downloadSourceflavorsExist = 'true';
        $fieldname = 'download-source-flavors';
        $flavor = "{$attachmentfield->flavor_type}/{$attachmentfield->flavor_subtype}";
        $metadata->processing->configuration->$fieldname = "{$flavor},security/*"; // Make sure ACL gets republished.
        $metadatajson = json_encode($metadata);

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($fileid);

        $api = new api();
        $res = $api->oc_post("/admin-ng/event/$eventid/assets", [
            "{$attachmentfield->asset_id}.0" => $file,
            "metadata" => $metadatajson
        ]);
        if ($api->get_http_code() >= 400) {
            throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
        }
        return $res;
    }

    /**
     * Starts a workflow in the opencast system.
     * @param string $eventid event id in the opencast system.
     * @param string $workflow identifier of the workflow to be started.
     * @return bool true, if successfully started.
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function start_workflow($eventid, $workflow, $params = array()) {
        if (!$workflow) {
            return false;
        }

        $api = new api();
        if (!$api->supports_api_level('v1.1.0')) {
            // Get mediapackage xml.
            $resourceopencast5 = '/assets/episode/' . $eventid;
            $api = new api();
            $mediapackage = $api->oc_get($resourceopencast5);

            // Start workflow.
            $resourceopencast5 = '/workflow/start';
            $params['definition'] = $workflow;
            $params['mediapackage'] = rawurlencode($mediapackage);

            $api = new api();
            $api->oc_post($resourceopencast5, $params);

            if ($api->get_http_code() != 200) {
                return false;
            }
        } else {
            // Start workflow.
            $resource = '/api/workflows';
            $params['workflow_definition_identifier'] = $workflow;
            $params['event_identifier'] = $eventid;

            $api = new api();
            $api->oc_post($resource, $params);

            if ($api->get_http_code() != 201) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether a workflow exists or not.
     *
     * @param $name id of workflow
     *
     * @return boolean
     */
    public function check_if_workflow_exists($name) {
        $workflows = $this->get_existing_workflows();

        return array_key_exists($name, $workflows);
    }

    /**
     * Retrieves all workflows from the OC system and parses them to be easily processable.
     *
     * @param string $tag if not empty the workflows are filter according to this tag.
     *
     * @return array of OC workflows. The keys represent the ID of the workflow,
     * while the value contains its displayname. This is either the description, if set, or the ID.
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_existing_workflows($tag = '') {
        if (!array_key_exists($tag, $this->workflows)) {

            $api = new api();
            if (!$api->supports_api_level('v1.1.0')) {
                $resourceopencast5 = '/workflow/definitions.json';
                $api = new api();
                $result = $api->oc_get($resourceopencast5);

                if ($api->get_http_code() === 200) {
                    $returnedworkflows = json_decode($result);
                    $this->workflows[$tag] = array();
                    foreach ($returnedworkflows->definitions->definition as $workflow) {
                        // Filter for specific tag.
                        if ($tag) {
                            if (!($workflow->tags &&
                                ($tag === $workflow->tags->tag ||
                                    is_array($workflow->tags->tag) &&
                                    in_array($tag, $workflow->tags->tag)))) {
                                continue;
                            }
                        }
                        if (object_property_exists($workflow, 'title') && !empty($workflow->title)) {
                            $this->workflows[$tag][$workflow->id] = $workflow->title;
                        } else {
                            $this->workflows[$tag][$workflow->id] = $workflow->id;
                        }
                    }
                } else {
                    return array();
                }
            } else {
                $resource = '/api/workflow-definitions';
                $api = new api();
                $resource .= '?filter=tag:'.$tag;
                $result = $api->oc_get($resource);
                if ($api->get_http_code() === 200) {
                    $returnedworkflows = json_decode($result);
                    $this->workflows[$tag] = array();
                    foreach ($returnedworkflows as $workflow) {
                        if (object_property_exists($workflow, 'title') && !empty($workflow->title)) {
                            $this->workflows[$tag][$workflow->identifier] = $workflow->title;
                        } else {
                            $this->workflows[$tag][$workflow->identifier] = $workflow->identifier;
                        }
                    }
                } else {
                    return array();
                }
            }
        }
        return $this->workflows[$tag];
    }

    /**
     * Can delete the event in opencast.
     *
     * @param object $video opencast video.
     */
    public function can_delete_event_assignment($video, $courseid) {

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
    public function trigger_delete_event($eventidentifier) {
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
    public function delete_event($eventidentifier) {

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
    public function get_course_videos_for_backup($courseid, $processingstates = ['SUCCEEDED']) {

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
    public function supports_api_level($level) {

        $api = new api();
        try {
            return $api->supports_api_level($level);
        } catch (\moodle_exception $e) {
            debugging('Api level '.$level.' not supported.');
            return false;
        }
        return false;
    }

    /**
     * If testing is set to true and we are in PHP_UNIT environment, a new instance of the apibridge will result in
     * a testable class. It also resets the current apibridge instance.
     * @param bool $testing true, if get_instance should return a testable.
     */
    public static function set_testing($testing) {
        self::$testing = $testing;
        self::get_instance(true);
    }

    //Metadata

    /**
     * The the allowance of the update metadata process
     * @param bool the capability of updating!
     */
    public function can_update_event_metadata($video, $courseid) {

        if (isset($video->processing_state) &&
            ($video->processing_state !== 'RUNNING' && $video->processing_state !== 'PAUSED')) {
            $context = \context_course::instance($courseid);
            return has_capability('block/opencast:addvideo', $context);
        }

        return false;
    }

    public function can_add_attachments($video, $courseid) {

        static $attachmentsconfigured = null;
        if ($attachmentsconfigured === null) {
            $attachfields = upload_helper::get_opencast_attachment_fields();
            $attachmentsconfigured = !empty($attachfields);
        }

        if (isset($video->processing_state) &&
            ($video->processing_state !== 'RUNNING' && $video->processing_state !== 'PAUSED')) {
            $context = \context_course::instance($courseid);
            return has_capability('block/opencast:addvideo', $context)
                    && $attachmentsconfigured;
        }

        return false;
    }

    /**
     * Get the event's metadata of the specified type
     * @param string $eventidentifier identifier of the event
     * @return stdClass $metadata
     */
    public function get_event_metadata($eventidentifier, $query = '') {
        $api = new api();
        $resource = '/api/events/' . $eventidentifier . '/metadata' . $query;
        $metadata = $api->oc_get($resource);

        if ($api->get_http_code() != 200) {
            return  $api->get_http_code();
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
    public function update_event_metadata($eventidentifier, $metadata) {


        $resource = '/api/events/' . $eventidentifier . '/metadata?type=dublincore/episode';

        $params['metadata'] = json_encode($metadata);
        $api = new api();
        $api->oc_put($resource, $params);

        if ($api->get_http_code() == 204) {
            return $this->update_metadata($eventidentifier);
        };
        return false;
    }

}
