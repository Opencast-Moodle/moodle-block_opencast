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
use block_opencast\task\process_duplicated_event_visibility_change;
use block_opencast_apibridge_testable;
use block_opencast_renderer;
use coding_exception;
use context_course;
use core\invalid_persistent_exception;
use core_user;
use dml_exception;
use local_chunkupload\local\chunkupload_file;
use moodle_exception;
use moodle_url;
use OpencastApi\Rest\OcIngest;
use stdClass;
use stored_file;
use tool_opencast\local\settings_api;
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
class apibridge {

    /** @var int Opencast instance id */
    private $ocinstanceid;

    /** @var string[] Placeholders related to users. */
    private static $userplaceholders = ['[USERNAME]', '[USERNAME_LOW]', '[USERNAME_UP]', '[USER_EMAIL]', '[USER_EXTERNAL_ID]'];

    /** @var bool True for tests */
    private static $testing = false;

    /** @var api the opencast tool api instance */
    public $api;

    /**
     * apibridge constructor.
     * @param int $ocinstanceid Opencast instance id.
     */
    private function __construct($ocinstanceid) {
        $this->ocinstanceid = $ocinstanceid;
        $this->api = api::get_instance($this->ocinstanceid);
    }

    /**
     * Get an instance of an object of this class. Create as a singleton.
     *
     * @param int $ocinstanceid Opencast instance id
     * @param boolean $forcenewinstance true, when a new instance should be created.
     * @return apibridge
     */
    public static function get_instance($ocinstanceid = null, $forcenewinstance = false) {
        static $apibridges = [];

        if (!$ocinstanceid) {
            $ocinstanceid = settings_api::get_default_ocinstance()->id;
        }

        if (array_key_exists($ocinstanceid, $apibridges) && !$forcenewinstance) {
            return $apibridges[$ocinstanceid];
        }

        // Use replacement of api bridge for test cases.
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && self::$testing) {
            $apibridge = new block_opencast_apibridge_testable();
            $apibridge->ocinstanceid = 1;
            $apibridges[1] = $apibridge;
            return $apibridge;
        }

        $apibridge = new apibridge($ocinstanceid);
        $apibridges[$ocinstanceid] = $apibridge;

        return $apibridge;
    }

    /**
     * Check, whether the Opencast API has been setup correctly.
     * This does not check if the Opencast server is up and running.
     * It just checks if the Opencast API configuration is fine by requesting an instance of the Opencast API from tool_opencast.
     *
     * @return boolean
     */
    public function check_api_configuration() {
        // Try to get an instance of the Opencast API from tool_opencast.
        try {
            $api = $this->get_instance($this->ocinstanceid);

            // If the API is not set up correctly, the constructor will throw an exception.
        } catch (moodle_exception $e) {
            return false;
        }

        // Otherwise the API should be set up correctly.
        return true;
    }

    /**
     * Sets up an api object with an ingest node as endpoint.
     *
     * @return OcIngest
     * @throws dml_exception
     * @throws moodle_exception
     * @throws opencast_connection_exception
     */
    private function get_ingest_api() {
        $this->api = api::get_instance($this->ocinstanceid, [], [], true);
        if (!property_exists($this->api->opencastapi, 'ingest')) {
            throw new opencast_connection_exception('ingest_endpoint_notfound', 'block_opencast');
        }
        return $this->api->opencastapi->ingest;
    }

    /**
     * Create a new media package via an ingest node.
     *
     * @return string Newly created mediapackage
     * @throws dml_exception
     * @throws moodle_exception
     * @throws opencast_connection_exception
     */
    public function ingest_create_media_package() {
        $ingestapi = $this->get_ingest_api();
        $response = $ingestapi->createMediaPackage();
        $code = $response['code'];
        $mediapackage = $response['body'];
        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }

        return $mediapackage;
    }

    /**
     * Add a catalog via an ingest node.
     * @param string $mediapackage Mediapackage to which the catalog is added
     * @param string $flavor Flavor of catalog
     * @param object $file Catalog as file
     * @return string
     * @throws dml_exception
     * @throws moodle_exception
     * @throws opencast_connection_exception
     */
    public function ingest_add_catalog($mediapackage, $flavor, $file) {
        $ingestapi = $this->get_ingest_api();
        $response = $ingestapi->addCatalog($mediapackage, $flavor, $file);
        $code = $response['code'];
        $newmediapackage = $response['body'];

        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }

        return $newmediapackage;
    }

    /**
     * Add a track via an ingest node.
     * @param string $mediapackage Mediapackage to which the track is added
     * @param string $flavor Flavor of track
     * @param object $file Track
     * @return string
     * @throws dml_exception
     * @throws moodle_exception
     * @throws opencast_connection_exception
     */
    public function ingest_add_track($mediapackage, $flavor, $file) {
        $ingestapi = $this->get_ingest_api();
        $response = $ingestapi->addTrack($mediapackage, $flavor, $this->get_upload_filestream($file));
        $code = $response['code'];
        $newmediapackage = $response['body'];

        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }

        return $newmediapackage;
    }

    /**
     * Adds an attachment via an ingest node.
     * @param string $mediapackage Mediapackage to which the attachment is added
     * @param string $flavor Flavor of attachment
     * @param object $file Attachment
     * @return string
     * @throws dml_exception
     * @throws moodle_exception
     * @throws opencast_connection_exception
     */
    public function ingest_add_attachment($mediapackage, $flavor, $file) {
        $ingestapi = $this->get_ingest_api();
        $response = $ingestapi->addAttachment($mediapackage, $flavor, $file);
        $code = $response['code'];
        $newmediapackage = $response['body'];

        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }

        return $newmediapackage;
    }

    /**
     * Ingests a mediapackage.
     * @param string $mediapackage Mediapackage
     * @param string $uploadworkflow workflow definition is to start after ingest
     * @return string Workflow instance that was started
     * @throws dml_exception
     * @throws moodle_exception
     * @throws opencast_connection_exception
     */
    public function ingest($mediapackage, $uploadworkflow = '') {
        $ingestapi = $this->get_ingest_api();

        if (empty($uploadworkflow)) {
            $uploadworkflow = get_config("block_opencast", "uploadworkflow_" . $this->ocinstanceid);
        }

        $uploadtimeout = get_config('block_opencast', 'uploadtimeout');
        if ($uploadtimeout !== false) {
            $timeout = intval($uploadtimeout);
            $response = $ingestapi->setRequestTimeout($timeout)->ingest($mediapackage, $uploadworkflow);
        } else {
            $response = $ingestapi->ingest($mediapackage, $uploadworkflow);
        }

        $code = $response['code'];
        $workflow = $response['body'];

        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }

        return $workflow;
    }

    /**
     * Copies either the file object of type CURLStringFile or PolyfillCURLStringFile to a temp filesytem directory,
     * and then perform the fopen. After all it deletes the temp file and returns the stream file.
     *
     * @param string $filename the filename to convert.
     * @param string $filedata the filedata to convert.
     * @return resource|false the file pointer resource.
     */
    public function get_upload_xml_file($filename, $filedata) {
        $filedata = (string)$filedata;
        $tempdir = make_temp_directory('xmlfiletoupload');
        $tempfilepath = tempnam($tempdir, 'tempup_') . $filename;
        file_put_contents($tempfilepath, $filedata);
        $filestream = fopen($tempfilepath, 'r');
        if (file_exists($tempfilepath)) {
            unlink($tempfilepath);
        }
        return $filestream;
    }

    /**
     * Get videos to show in block. Items are limited and ready to use by renderer.
     * Note that we try to receive one item more than configurated to decide whether
     * to display a "more videos" link.
     *
     * @param int $courseid
     * @param bool $withmetadata
     * @return stdClass
     */
    public function get_block_videos($courseid, $withmetadata = false) {
        if (defined('BEHAT_SITE_RUNNING')) {
            $withmetadata = true;
        }

        $result = new stdClass();
        $result->count = 0;
        $result->more = false;
        $result->videos = [];
        $result->error = 0;

        $series = $this->get_course_series($courseid);

        if (count($series) == 0) {
            return $result;
        }

        $limitvideosconfig = intval(get_config('block_opencast', 'limitvideos_' . $this->ocinstanceid));
        $allvideos = [];

        foreach ($series as $s) {
            $params = [
                'sign' => true,
                'withacl' => true,
                'withmetadata' => $withmetadata === true,
                'withpublications' => true,
                'sort' => [
                    'start_date' => 'DESC',
                ],
            ];

            if ($limitvideosconfig > 0) {
                // Try to fetch one more to decide whether display "more link" is necessary.
                $params['limit'] = $limitvideosconfig + 1;
            }

            $response = $this->api->opencastapi->eventsApi->getBySeries($s->series, $params);
            $code = $response['code'];

            if ($code === 0) {
                throw new opencast_connection_exception('connection_failure', 'block_opencast');
            } else if ($code != 200) {
                throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
            }

            $videos = $response['body'];

            if (!empty($videos)) {
                $allvideos = array_merge($allvideos, $videos);
            }
        }

        if (!$allvideos) {
            return $result;
        }

        usort($allvideos, function ($a, $b) {
            return (int)$a->start - (int)$b->start;
        });

        $result->count = count($allvideos);
        $result->more = ($result->count > $limitvideosconfig);

        // If we have received more than limit count of videos remove one.
        if ($result->more && $limitvideosconfig > 0) {
            $allvideos = array_slice($allvideos, 0, $limitvideosconfig);
        }

        if ($result->error == 0) {
            foreach ($allvideos as $video) {
                $this->extend_video_status($video);
            }
        }

        $result->videos = $allvideos;

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
        $result = new stdClass();
        $result->videos = [];
        $result->error = 0;

        $series = $this->get_default_course_series($courseid);

        if (!isset($series)) {
            return $result;
        }

        return $this->get_series_videos($series->identifier, $sortcolumns);
    }

    /**
     * Get all the videos (events) for a series.
     *
     * @param string $series
     * @param string $sortcolumns
     * @param bool $withmetadata
     * @return stdClass
     */
    public function get_series_videos($series, $sortcolumns = null, $withmetadata = false) {
        if (defined('BEHAT_SITE_RUNNING')) {
            $withmetadata = true;
        }

        $result = new stdClass();
        $result->videos = [];
        $result->error = 0;

        $params = [
            'sign' => true,
            'withacl' => true,
            'withmetadata' => $withmetadata === true,
            'withpublications' => true,
        ];

        if ($sortcolumns) {
            $sort = [];
            foreach ($sortcolumns as $key => $sortorder) {
                $sort[$key] = (SORT_ASC == $sortorder) ? 'ASC' : 'DESC';
            }
            $params['sort'] = $sort;
        }

        $response = $this->api->opencastapi->eventsApi->getBySeries($series, $params);
        $code = $response['code'];

        if ($code != 200) {
            $result->error = $code;
            return $result;
        }

        $videos = $response['body'];

        if (empty($videos)) {
            return $result;
        }

        $result->videos = $videos;

        if ($result->error == 0) {
            foreach ($videos as $video) {
                $this->extend_video_status($video);
                $this->set_download_state($video);
                $this->set_access_state($video);
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
     * @param stdClass $video The video object, which should be checked.
     */
    private function extend_video_status(&$video) {
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
     * @param stdClass $video Video to be updated
     */
    private function set_download_state(&$video) {
        if (in_array(get_config('block_opencast', 'download_channel_' . $this->ocinstanceid), $video->publication_status)) {
            $video->is_downloadable = true;
        } else {
            $video->is_downloadable = false;
        }
    }

    /**
     * Checks if a video can be accessed directly and saves this state.
     * @param stdClass $video Video to be updated
     */
    private function set_access_state(&$video) {
        if (in_array(get_config('block_opencast', 'direct_access_channel_' . $this->ocinstanceid), $video->publication_status)) {
            $video->is_accessible = true;
        } else {
            $video->is_accessible = false;
        }
    }

    /**
     * Retrieves a video from Opencast.
     * @param string $identifier Event id
     * @param bool $withpublications If true, publications are included
     * @param bool $withacl If true, ACLs are included
     * @param bool $includingmedia If true, media files are included
     * @return stdClass Video
     */
    public function get_opencast_video($identifier, bool $withpublications = false, bool $withacl = false,
                                       bool $includingmedia = false) {
        $result = new stdClass();
        $result->video = false;
        $result->error = 0;

        $params = [
            'withacl' => $withacl,
            'withpublications' => $withpublications,
        ];

        $response = $this->api->opencastapi->eventsApi->get($identifier, $params);
        $code = $response['code'];

        if ($code != 200) {
            $result->error = $code;
            return $result;
        }

        $video = $response['body'];

        if (empty($video)) {
            return $result;
        }

        // Enrich processing state.
        $this->extend_video_status($video);
        $this->set_download_state($video);
        $this->set_access_state($video);
        // Including media into the video object.
        if ($includingmedia) {
            $media = null;
            $response = $this->api->opencastapi->eventsApi->getMedia($identifier);
            $code = $response['code'];
            if ($code === 200) {
                $media = $response['body'];
            }
            $video->media = $media;
        }

        $result->video = $video;

        return $result;
    }

    /**
     * API call to check, whether the course related group exists in opencast system.
     *
     * @param int $courseid
     * @param int $userid
     * @return object group object of NULL, if group does not exist.
     */
    protected function get_acl_group($courseid, $userid) {
        $groupname = $this->replace_placeholders(get_config('block_opencast',
            'group_name_' . $this->ocinstanceid), $courseid, null, $userid)[0];
        $groupidentifier = $this->get_course_acl_group_identifier($groupname);

        $response = $this->api->opencastapi->groupsApi->get($groupidentifier);
        $group = $response['body'];
        return $group;
    }

    /**
     * Returns the group identifier from a group name.
     *
     * @param String $groupname
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
     * @param int $userid
     */
    protected function create_acl_group($courseid, $userid) {
        $name = $this->replace_placeholders(get_config('block_opencast',
            'group_name_' . $this->ocinstanceid), $courseid, null, $userid)[0];
        $description = 'ACL for users in Course with id ' . $courseid . ' from site "Moodle"';
        $roles = [
            'ROLE_API_SERIES_VIEW',
            'ROLE_API_EVENTS_VIEW',
        ];

        $response = $this->api->opencastapi->groupsApi->create($name, $description, $roles);
        $code = $response['code'];

        if ($code >= 400) {
            throw new moodle_exception('serverconnectionerror', 'tool_opencast');
        }
    }

    /**
     * Check, whether the related group exists to given course id. If not exists thatn try to create
     * a group in opencast system.
     *
     * @param int $courseid
     * @param int $userid
     * @return object group object.
     * @throws opencast_state_exception
     */
    public function ensure_acl_group_exists($courseid, $userid) {

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
    public function store_group_access($eventid, $groups) {
        try {
            $groupaccess = groupaccess::get_record(['opencasteventid' => $eventid, 'ocinstanceid' => $this->ocinstanceid]);
            if ($groupaccess) {
                if (empty($groups)) {
                    $groupaccess->delete();
                } else {
                    $groupaccess->set('moodlegroups', implode(',', $groups));
                    $groupaccess->update();
                }
            } else {
                $groupaccess = new groupaccess();
                $groupaccess->set('ocinstanceid', $this->ocinstanceid);
                $groupaccess->set('opencasteventid', $eventid);
                $groupaccess->set('moodlegroups', implode(',', $groups));
                $groupaccess->create();
            }
        } catch (moodle_exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Retrieves the id of the series, which is stored in the admin tool.
     *
     * @param int $courseid id of the course.
     * @param bool $createifempty Create a series on-the-fly if there isn't a series stored yet.
     * @param int $userid
     * @return string id of the series
     */
    public function get_stored_seriesid($courseid, $createifempty = false, $userid = null) {
        // Get series mapping.
        $mapping = seriesmapping::get_record(['ocinstanceid' => $this->ocinstanceid,
            'courseid' => $courseid, 'isdefault' => '1', ]);

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
     * @param bool $withacl If true, ACLs are included
     * @return null|stdClass series if it exists in the opencast system.
     */
    public function get_series_by_identifier($seriesid, bool $withacl = false) {
        if (empty($seriesid)) {
            return null;
        }

        $response = $this->api->opencastapi->seriesApi->get($seriesid, $withacl);
        $code = $response['code'];

        // If something went wrong, we return false.
        if ($code != 200) {
            return null;
        }

        return $response['body'];
    }

    /**
     * API call to check, whether series exists in opencast system.
     *
     * @param string[] $allseries
     * @return null|string id of the series id if it exists in the opencast system.
     */
    public function get_multiple_series_by_identifier($allseries) {
        $params = [];
        $filter = [];
        foreach ($allseries as $series) {
            if (isset($series->series)) {
                $filter['identifier'][] = $series->series;
            } else {
                $filter['identifier'][] = $series;
            }
        }
        if (!empty($filter)) {
            $params['filter'] = $filter;
        }

        $response = $this->api->opencastapi->seriesApi->getAll($params);
        $code = $response['code'];

        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }

        $series = $response['body'];
        return $series;
    }

    /**
     * API call to check, whether the course related series exists in opencast system.
     *
     * @param int $courseid
     * @return null|string id of the series id if it exists in the opencast system.
     */
    public function get_default_course_series($courseid) {
        if ($seriesid = $this->get_stored_seriesid($courseid)) {
            $response = $this->api->opencastapi->seriesApi->get($seriesid);
            if ($response['code'] != 200) {
                return null;
            }
            $series = $response['body'];
            return $series;
        }
        return null;
    }

    /**
     * Returns the record list of the course series.
     * @param int $courseid
     * @return array
     * @throws dml_exception
     */
    public function get_course_series($courseid) {
        global $DB;
        // We do an intense look-up into the series records, to avoid redundancy.
        $allcourseseries = $DB->get_records('tool_opencast_series',
            ['ocinstanceid' => $this->ocinstanceid, 'courseid' => $courseid]);
        $tempholder = [];
        $defaultseriesnum = 0;
        foreach ($allcourseseries as $courseserie) {
            if (empty($courseserie->series)) {
                continue;
            }
            if (!array_key_exists($courseserie->series, $tempholder)) {
                $tempholder[$courseserie->series] = $courseserie;
                if (boolval($courseserie->isdefault)) {
                    $defaultseriesnum++;
                }
            } else {
                // This is the place, where should not happen, namely having 2 identical series.
                // We replace swap them if the new one is default and old one isn't.
                if (boolval($courseserie->isdefault) && !boolval($tempholder[$courseserie->series]->isdefault)) {
                    $tempholder[$courseserie->series] = $courseserie;
                    $defaultseriesnum++;
                }
            }
        }
        // We throw an exception, if there are more than one default series.
        if ($defaultseriesnum > 1) {
            throw new moodle_exception('morethanonedefaultserieserror', 'block_opencast');
        }
        return !empty($tempholder) ? array_values($tempholder) : [];
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
     * @param int $userid
     *
     * @return string[]
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function replace_placeholders($name, $courseid, $groups = null, $userid = null) {
        global $SITE;

        // Skip course related placeholders if courseid is site id.
        if (intval($courseid) === intval($SITE->id)) {
            if (strpos($name, '[COURSENAME]') !== false ||
                strpos($name, '[COURSEID]') !== false ||
                strpos($name, '[COURSEGROUPID]') !== false) {
                return [null];
            }
        }

        $coursename = get_course($courseid)->fullname;
        $title = str_replace('[COURSENAME]', $coursename, $name);
        $title = str_replace('[COURSEID]', $courseid, $title);

        // Replace user-related placeholders.
        foreach (self::$userplaceholders as $placeholder) {
            if (strpos($title, $placeholder) !== false) {
                if (!$userid) {
                    return [];
                }

                $user = core_user::get_user($userid, '*', MUST_EXIST);
                switch ($placeholder) {
                    case '[USERNAME]':
                        $title = str_replace('[USERNAME]', $user->username, $title);
                        break;
                    case '[USERNAME_LOW]':
                        $title = str_replace('[USERNAME_LOW]', strtolower($user->username), $title);
                        break;
                    case '[USERNAME_UP]':
                        $title = str_replace('[USERNAME_UP]', strtoupper($user->username), $title);
                        break;
                    case '[USER_EMAIL]':
                        $title = str_replace('[USER_EMAIL]', $user->email, $title);
                        break;
                    case '[USER_EXTERNAL_ID]':
                        $title = str_replace('[USER_EXTERNAL_ID]', $user->idnumber, $title);
                        break;
                }
            }
        }

        $result = [];

        if (strpos($name, '[COURSEGROUPID]') !== false) {
            if (is_array($groups)) {
                foreach ($groups as $groupid) {
                    $result[] = str_replace('[COURSEGROUPID]', 'G' . $groupid, $title);
                }
            } else {
                $result[] = str_replace('[COURSEGROUPID]', $courseid, $title);
            }
        } else {
            $result[] = $title;
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
    private function get_pattern_for_group_placeholder($name, $courseid) {
        $coursename = get_course($courseid)->fullname;
        $title = str_replace('[COURSENAME]', $coursename, $name);
        $title = str_replace('[COURSEID]', $courseid, $title);
        return '/' . str_replace('[COURSEGROUPID]', 'G\\d*', $title) . '/';
    }

    /**
     * Returns the default series name for a course.
     * @param int $courseid id of the course.
     * @param int $userid
     * @return string default series title.
     */
    public function get_default_seriestitle($courseid, $userid) {
        $title = get_config('block_opencast', 'series_name_' . $this->ocinstanceid);
        return self::replace_placeholders($title, $courseid, null, $userid)[0];
    }

    /**
     * API call to create a series for given course.
     * @param int $courseid Course id
     * @param null $metadatafields
     * @param null $userid
     * @return bool | stdClass  tells if the creation of the series was successful.
     * @throws coding_exception
     * @throws invalid_persistent_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function create_course_series($courseid, $metadatafields = null, $userid = null) {
        $mapping = seriesmapping::get_record(['ocinstanceid' => $this->ocinstanceid,
            'courseid' => $courseid, 'isdefault' => '1', ]);

        $isdefault = $mapping ? false : true;

        $metadata = [];
        $metadata['label'] = "Opencast Series Dublincore";
        $metadata['flavor'] = "dublincore/series";

        if (is_null($metadatafields)) {
            $metadatafields = [];
            $metadatafields[] = ['id' => 'title', 'value' => $this->get_default_seriestitle($courseid, $userid)];
        }

        $metadata['fields'] = $metadatafields;

        $metadatastr = json_encode([$metadata]);

        $acl = [];
        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                $acl[] = (object)['allow' => true, 'action' => $action,
                    'role' => $this->replace_placeholders($role->rolename, $courseid, null, $userid)[0], ];
            }
        }

        $aclstr = json_encode(array_values($acl));

        $response = $this->api->opencastapi->seriesApi->create($metadatastr, $aclstr);
        $code = $response['code'];
        if ($code >= 400 || $code < 200) {
            throw new moodle_exception('serverconnectionerror', 'tool_opencast');
        }
        $series = $response['body'];

        if (isset($series) && object_property_exists($series, 'identifier')) {
            $title = $metadata['fields'][array_search('title', array_column($metadata['fields'], 'id'))]['value'];

            $mapping = new seriesmapping();
            $mapping->set('ocinstanceid', $this->ocinstanceid);
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $series->identifier);
            $mapping->set('isdefault', $isdefault);
            $mapping->create();
            $rec = $mapping->to_record();
            $rec->seriestitle = $title;
            return $rec;
        }
        return false;
    }

    /**
     * Check, whether the related series exists to given course id. If not exists than try to create
     * a group in opencast system.
     *
     * @param int $courseid
     * @param int $userid
     *
     * @return string series object.
     * @throws opencast_state_exception
     */
    public function ensure_course_series_exists($courseid, $userid) {

        $series = $this->get_default_course_series($courseid);

        if (!isset($series)) {
            $this->create_course_series($courseid, null, $userid);
            // Check success.
            $series = $this->get_default_course_series($courseid);
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
     * @param int $userid
     */
    public function update_course_series($courseid, $seriesid, $userid) {
        $mapping = seriesmapping::get_record(['ocinstanceid' => $this->ocinstanceid,
            'courseid' => $courseid, 'isdefault' => '1', ]);

        if (!$mapping) {
            $mapping = new seriesmapping();
            $mapping->set('ocinstanceid', $this->ocinstanceid);
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $seriesid);
            $mapping->set('isdefault', '1');
            $mapping->create();
        } else {
            $mapping->set('series', $seriesid);
            $mapping->update();
        }

        // Update Acl roles.
        $response = $this->api->opencastapi->seriesApi->getAcl($seriesid);
        $acl = $response['body'];
        $defaultaclstr = json_encode(array_values($acl));

        if (!is_array($acl)) {
            throw new moodle_exception('invalidacldata', 'block_opencast');
        }

        $roles = $this->getroles();
        foreach ($roles as $role) {
            if (strpos($role->rolename, '[USERNAME]') !== false ||
                strpos($role->rolename, '[USERNAME_LOW]') !== false ||
                strpos($role->rolename, '[USERNAME_UP]') !== false) {
                // Add new user as well.
                foreach ($role->actions as $action) {
                    $acl[] = (object)['allow' => true,
                        'role' => self::replace_placeholders($role->rolename, $courseid, null, $userid)[0],
                        'action' => $action, ];
                }

            } else {
                foreach ($role->actions as $action) {
                    foreach ($acl as $key => $aclval) {
                        if (($aclval->action == $action) && ($aclval->role == $role)) {
                            unset($acl[$key]);
                        }
                    }

                    $acl[] = (object)['allow' => true,
                        'role' => self::replace_placeholders($role->rolename, $courseid)[0],
                        'action' => $action, ];
                }
            }
        }

        $aclstr = json_encode(array_values($acl));

        // Acl roles have not changed.
        if ($aclstr == ($defaultaclstr)) {
            return true;
        }

        $response = $this->api->opencastapi->seriesApi->updateAcl($seriesid, $aclstr);
        return ($response['code'] == 204);
    }

    /**
     * Remove course series ID, because it was set blank.
     * No changes in Opencast are done, due to this action.
     *
     * @param int $courseid Course ID
     */
    public function unset_course_series($courseid) {
        $mapping = seriesmapping::get_record(['ocinstanceid' => $this->ocinstanceid,
            'courseid' => $courseid, 'isdefault' => '1', ]);

        if ($mapping) {
            $mapping->delete();
        }
    }

    /**
     * Checks if the series ID exists in the Opencast system.
     * @param string $seriesid Series id
     * @return bool true, if the series exists. Otherwise false.
     * @throws dml_exception
     * @throws moodle_exception if there is no connection to the server.
     */
    public function ensure_series_is_valid($seriesid) {
        $response = $this->api->opencastapi->seriesApi->get($seriesid);
        $code = $response['code'];

        if ($code === 404) {
            return false;
        }

        if ($code >= 400) {
            throw new moodle_exception('serverconnectionerror', 'tool_opencast');
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
            $response = $this->api->opencastapi->eventsApi->get($opencastid);
            $event = $response['body'];

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

        $event = new event();

        // Get initial visibility object.
        $initialvisibility = visibility_helper::get_initial_visibility($job);

        // Add the event roles from visibility object.
        foreach ($initialvisibility->roles as $acl) {
            $event->add_acl($acl->allow, $acl->action, $acl->role);
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
            throw new moodle_exception('invalidfiletoupload', 'tool_opencast');
        }

        if ($job->metadata) {
            foreach (json_decode($job->metadata) as $metadata) {
                $event->add_meta_data($metadata->id, $metadata->value);
            }
        }

        $acl = $event->get_json_acl();
        $metadata = $event->get_meta_data();
        $processing = $event->get_processing($this->ocinstanceid);
        $scheduling = '';
        $presenter = null;
        $presentation = null;
        if ($event->get_presenter()) {
            $presenter = $this->get_upload_filestream($event->get_presenter());
        }
        if ($event->get_presentation()) {
            $presentation = $this->get_upload_filestream($event->get_presentation());
        }

        $uploadtimeout = get_config('block_opencast', 'uploadtimeout');
        if ($uploadtimeout !== false) {
            $timeout = intval($uploadtimeout);
            $response = $this->api->opencastapi->eventsApi->setRequestTimeout($timeout)->create(
                $acl,
                $metadata,
                $processing,
                $scheduling,
                $presenter,
                $presentation,
                null
            );
        } else {
            $response = $this->api->opencastapi->eventsApi->create(
                $acl,
                $metadata,
                $processing,
                $scheduling,
                $presenter,
                $presentation,
                null
            );
        }

        $code = $response['code'];
        $result = $response['body'];
        if ($code != 201) {
            // In case the metadata field is invalid, $result contains the following pattern.
            $errorpattern = "/Cannot find a metadata field with id '([\w]+)' from Catalog with Flavor 'dublincore\/episode'./";
            if (preg_match($errorpattern, $result)) {
                // If the process fails due to invalid metadata field, more specific error message will be thrown.
                throw new moodle_exception('invalidmetadatafield', 'block_opencast', null, $result);
            }
            throw new moodle_exception('serverconnectionerror', 'tool_opencast');
        }

        return $result;
    }

    /**
     * Copies either the file object of type stored_file or chunkupload_file to a temp filesytem directory,
     * and then perform the fopen. After all it deletes the temp file and returns the stream file.
     *
     * @param stored_file|chunkupload_file $file the file to convert.
     * @param string $type the type of the file to get filestream for.
     * @return resource|false the file pointer resource.
     */
    public function get_upload_filestream($file, $type = 'video') {
        $tempdirname = "oc{$type}toupload";
        $tempdir = make_temp_directory($tempdirname);
        $tempfilepath = tempnam($tempdir, 'block_opencast_' . $type . '_upload') . '_' . $file->get_filename();
        $filestream = null;
        if ($file instanceof stored_file) {
            if ($file->copy_content_to($tempfilepath)) {
                $filestream = fopen($tempfilepath, 'r');
            }
        } else if (class_exists('\local_chunkupload\local\chunkupload_file') &&
            $file instanceof chunkupload_file) {
            if (copy($file->get_fullpath(), $tempfilepath)) {
                $filestream = fopen($tempfilepath, 'r');
            }
        }
        if (file_exists($tempfilepath)) {
            unlink($tempfilepath);
        }
        return $filestream;
    }

    /**
     *
     * Returns an array of acl roles. The actions field of each entry contains an array of trimmed action names
     * for the specific role.
     *
     * @param string|bool $permanent If true, only permanent roles are returned
     * @return array of acl roles.
     * @throws dml_exception A DML specific exception is thrown for any errors.
     */
    public function getroles($permanent = null) {
        $roles = json_decode(get_config('block_opencast', 'roles_' . $this->ocinstanceid));
        if (empty($roles)) {
            return [];
        }
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
     * @param stdClass $job Job to be checked
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

        // Flag as newly created.
        $event->newlycreated = true;

        return $event;
    }

    /**
     * Post group to control access.
     *
     * @param string $eventidentifier
     * @param int $courseid
     * @param int $userid
     *
     * @return boolean true if succeeded
     */
    public function ensure_acl_group_assigned($eventidentifier, $courseid, $userid) {
        $response = $this->api->opencastapi->eventsApi->getAcl($eventidentifier);
        $jsonacl = $response['body'];

        $event = new event();
        $event->set_json_acl($jsonacl);

        $roles = $this->getroles();
        foreach ($roles as $role) {
            foreach ($role->actions as $action) {
                foreach (self::replace_placeholders($role->rolename, $courseid, $eventidentifier, $userid) as $acl) {
                    $event->add_acl(true, $action, $acl);
                }
            }
        }

        $aclstr = $event->get_json_acl();

        // Acl roles have not changed.
        if ($aclstr == json_encode(array_values($jsonacl))) {
            return true;
        }

        $response = $this->api->opencastapi->eventsApi->updateAcl($eventidentifier, $aclstr);
        $code = $response['code'];
        if ($code != 204) {
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
    public function can_delete_acl_group_assignment($video, $courseid) {
        $config = get_config('block_opencast', 'allowunassign_' . $this->ocinstanceid);

        if (!$config) {
            return false;
        }

        if (!isset($video->processing_state) || ($video->processing_state != 'SUCCEEDED')) {
            return false;
        }

        $context = context_course::instance($courseid);

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
    public function delete_acl_group_assigned($eventidentifier, $courseid) {
        $event = new event();

        $grouprole = api::get_course_acl_role($courseid);

        $response = $this->api->opencastapi->eventsApi->deleteSingleAcl($eventidentifier, 'read', $grouprole);
        if ($response['code'] != 204) {
            return false;
        }

        $response = $this->api->opencastapi->eventsApi->getAcl($eventidentifier);
        if ($response['code'] != 200) {
            return false;
        }
        $acls = $response['body'];

        $event->set_json_acl($acls);

        // Adapt course series.
        if (!$courseid = $event->get_next_series_courseid()) {
            $this->assign_series($eventidentifier, '');
        }

        $series = $this->ensure_course_series_exists($courseid, null);

        return $this->assign_series($eventidentifier, $series->identifier);
    }

    /**
     * Changes the visibility of videos by updating the ACL list.
     * @param string $eventidentifier Event id
     * @param int $courseid Course id
     * @param int $visibility Visibility to be applied
     * @param array|null $groups Groups
     * @return string identifier of the notification string to be presented to the user.
     */
    public function change_visibility($eventidentifier, $courseid, $visibility, $groups = null) {
        $oldgroups = groupaccess::get_record(['opencasteventid' => $eventidentifier, 'ocinstanceid' => $this->ocinstanceid]);
        $oldgroupsarray = $oldgroups ? explode(',', $oldgroups->get('moodlegroups')) : [];

        $allowedvisibilitystates = [block_opencast_renderer::VISIBLE,
            block_opencast_renderer::HIDDEN, block_opencast_renderer::GROUP, ];
        if (!in_array($visibility, $allowedvisibilitystates)) {
            throw new coding_exception('Invalid visibility state.');
        }

        $oldvisibility = $this->is_event_visible($eventidentifier, $courseid);

        // Only use transmitted groups if the status is group.
        if ($visibility !== block_opencast_renderer::GROUP) {
            $groups = [];
        }

        // If there is no change in the status or in the group arrays, we can stop here.
        if ($oldvisibility === $visibility) {
            if ($visibility !== block_opencast_renderer::GROUP || $groups === $oldgroupsarray) {
                return 'aclnothingtobesaved';
            }
        }

        // Update group access.
        if ($groups !== $oldgroupsarray) {
            $this->store_group_access($eventidentifier, $groups);
        }

        $response = $this->api->opencastapi->eventsApi->getAcl($eventidentifier);
        $jsonacl = $response['body'];

        $event = new event();
        $event->set_json_acl($jsonacl);

        // Remove acls.
        if ($oldvisibility === block_opencast_renderer::MIXED_VISIBILITY) {
            $oldacls = [];
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

        $aclstoupdate = $event->get_json_acl();
        // Acl roles have not changed.
        if ($aclstoupdate == json_encode(array_values($jsonacl))) {
            return 'aclnothingtobesaved';
        }

        $response = $this->api->opencastapi->eventsApi->updateAcl($eventidentifier, $aclstoupdate);
        if ($response['code'] >= 400) {
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
    public function assign_series($eventidentifier, $seriesidentifier) {
        $metadata = json_encode([['id' => 'isPartOf', 'value' => $seriesidentifier]]);
        $response = $this->api->opencastapi->eventsApi->updateMetadata($eventidentifier, 'dublincore/episode', $metadata);
        return ($response['code'] == 204);
    }

    /**
     * Returns the expected set of non-permanent acl rules for the given status in the context of an event.
     * Can be used for comparision with the actual set of acl rules.
     * @param int $courseid id of the course the event belongs to.
     * @param int $visibility visibility of the event.
     * @param array|null $groups array of group ids used for replacing the placeholders
     * @return array of objects representing acl rules, each with the fields 'allow', 'action' and 'role'.
     * @throws dml_exception
     * @throws coding_exception In case of an invalid visibility status. Only [0,1,2] are allowed.
     */
    private function get_non_permanent_acl_rules_for_status($courseid, $visibility, $groups = null) {
        return $this->get_acl_rules_for_status($courseid, $visibility, false, $groups);
    }

    /**
     * Returns the expected set of permanent acl rules for the given status in the context of an event.
     * Can be used for comparision with the actual set of acl rules.
     * @param int $courseid id of the course the event belongs to.
     * @param int $visibility visibility of the event.
     * @param array|null $groups array of group ids used for replacing the placeholders
     * @param bool $forceonhidden flag to force return the acls when hidden.
     * @return array of objects representing acl rules, each with the fields 'allow', 'action' and 'role'.
     * @throws dml_exception
     * @throws coding_exception In case of an invalid visibility status. Only [0,1,2] are allowed.
     */
    private function get_permanent_acl_rules_for_status($courseid, $visibility, $groups = null, $forceonhidden = false) {
        return $this->get_acl_rules_for_status($courseid, $visibility, true, $groups, $forceonhidden);
    }

    /**
     * Returns the expected set of acl rules for the given status in the context of an event.
     * Can be used for comparision with the actual set of acl rules.
     * @param int $courseid id of the course the event belongs to.
     * @param int $visibility visibility of the event.
     * @param bool $permanent whether to get permanent or non-permanent acl rules.
     * @param array|null $groups array of group ids used for replacing the placeholders
     * @param bool $forceonhidden flag to force return the acls when hidden.
     * @return array of objects representing acl rules, each with the fields 'allow', 'action' and 'role'.
     * @throws dml_exception
     * @throws coding_exception In case of an invalid visibility status. Only [0,1,2] are allowed.
     */
    private function get_acl_rules_for_status($courseid, $visibility, $permanent, $groups = null, $forceonhidden = false) {
        $roles = $this->getroles($permanent ? 1 : 0);

        $result = [];

        switch ($visibility) {
            case block_opencast_renderer::VISIBLE:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        $rolenameformatted = self::replace_placeholders($role->rolename, $courseid)[0];
                        // Might return null if USERNAME cannot be replaced.
                        if ($rolenameformatted) {
                            $result[] = (object)[
                                'allow' => true,
                                'action' => $action,
                                'role' => $rolenameformatted,
                            ];
                        }
                    }
                }
                break;
            case block_opencast_renderer::HIDDEN:
                if ($permanent && $forceonhidden) {
                    foreach ($roles as $role) {
                        foreach ($role->actions as $action) {
                            $rolenameformatted = self::replace_placeholders($role->rolename, $courseid)[0];
                            // Might return null if USERNAME cannot be replaced.
                            if ($rolenameformatted) {
                                $result[] = (object)[
                                    'allow' => true,
                                    'action' => $action,
                                    'role' => $rolenameformatted,
                                ];
                            }
                        }
                    }
                }
                break;
            case block_opencast_renderer::GROUP:
                foreach ($roles as $role) {
                    foreach ($role->actions as $action) {
                        foreach (self::replace_placeholders($role->rolename, $courseid, $groups) as $rule) {
                            if ($rule) {
                                $result[] = (object)[
                                    'allow' => true,
                                    'action' => $action,
                                    'role' => $rule,
                                ];
                            }
                        }
                    }
                }
                break;
            default:
                throw new coding_exception('The provided visibility status is not valid!');
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
    public function is_event_visible($eventidentifier, $courseid) {
        $response = $this->api->opencastapi->eventsApi->getAcl($eventidentifier);
        $jsonacl = $response['body'];
        $event = new event();
        $event->set_json_acl($jsonacl);

        $groups = groupaccess::get_record(['opencasteventid' => $eventidentifier, 'ocinstanceid' => $this->ocinstanceid]);
        $groupsarray = $groups ? explode(',', $groups->get('moodlegroups')) : [];

        $visibleacl = $this->get_non_permanent_acl_rules_for_status($courseid, block_opencast_renderer::VISIBLE);
        $groupacl = $this->get_non_permanent_acl_rules_for_status($courseid, block_opencast_renderer::GROUP, $groupsarray);

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
            foreach ($jsonacl as $acl) {
                if (preg_match($pattern, $acl->role)) {
                    $hasnogroupacls = false;
                }
            }
        }
        // If all non permanent acls for visibility are set the event is visible.
        if ($hasallvisibleacls) {
            return block_opencast_renderer::VISIBLE;
        } else if (!empty($groupsarray) && $hasallgroupacls && !$hasaclnotingroup) {
            // If we have groups and the acl rules for each group is present and we do not have non-permanent acls,
            // which do not belong to group visibility, then visibility is group.
            return block_opencast_renderer::GROUP;
        } else if (empty($groupsarray) && $hasnogroupacls & $hasnovisibleacls) {
            // The visibility is hidden if we have no groupaccess and
            // if there is no acl for group or full visibility in the set.
            return block_opencast_renderer::HIDDEN;
        } else {
            // In all other cases we have mixed visibility.
            return block_opencast_renderer::MIXED_VISIBILITY;
        }
    }

    /**
     * Triggers the workflow to update the metadata in opencast.
     * This is necessary, when ACL rules of an event were updated in order to republish the video with the correct
     * access rights.
     * @param string $eventid id of the event the metadata should be updated for.
     * @return bool true, if the workflow was successfully started.
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function update_metadata($eventid) {
        $video = $this->get_opencast_video($eventid);

        if ($video->error === 0) {
            // Don't start workflow for scheduled videos.
            if ($video->video->processing_state !== "PLANNED") {
                $workflow = get_config('block_opencast', 'workflow_roles_' . $this->ocinstanceid);

                if (!$workflow) {
                    return true;
                }
                return $this->start_workflow($eventid, $workflow);
            }
            return true;
        }
        return false;
    }

    /**
     * Starts a workflow in the opencast system.
     * @param string $eventid event id in the opencast system.
     * @param string $workflow identifier of the workflow to be started.
     * @param array $params (optional) The workflow configuration.
     * @param bool $returnworkflowid (optional) Return the workflow ID instead of just a boolean.
     * @return bool|int false if the workflow was not successfully started;
     *                  true or the workflow ID (if $returnworkflowid was set) if the workflow was successfully started.
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function start_workflow($eventid, $workflow, $params = [], $returnworkflowid = false) {
        if (!$workflow) {
            return false;
        }
        // Start workflow.
        $configuration = isset($params['configuration']) ? $params['configuration'] : [];
        $withoperations = isset($params['withoperations']) ? $params['withoperations'] : false;
        $withconfiguration = isset($params['withconfiguration']) ? $params['withconfiguration'] : false;
        $response = $this->api->opencastapi->workflowsApi->run(
            $eventid,
            $workflow,
            $configuration,
            $withoperations,
            $withconfiguration
        );
        if ($response['code'] != 201) {
            return false;
        }
        $returnobject = $response['body'];
        // If requested, return the workflow ID now instead of just a boolean at the end of the function.
        if ($returnworkflowid == true) {
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
    public function check_if_workflow_exists($name) {
        $workflows = $this->get_existing_workflows();

        return array_key_exists($name, $workflows);
    }

    /**
     * Retrieves all workflows from the OC system and parses them to be easily processable. Multi-tags could be defined.
     * @param array $tags if not empty the workflows are filter according to the list of tags.
     * @param bool $onlynames If only the names of the workflows should be returned
     * @param false $withconfigurations If true, the configurations are included
     * @return array of OC workflows. The keys represent the ID of the workflow,
     * while the value contains its displayname. This is either the description, if set, or the ID. If not $onlynames,
     * the workflows details are also included.
     * @throws moodle_exception
     */
    public function get_existing_workflows($tags = [], $onlynames = true, $withconfigurations = false) {
        $workflows = [];

        // Make sure that the tags are trimmed.
        if (!empty($tags)) {
            $tags = array_map('trim', $tags);
        }

        $queryparams = [];
        // If only one or no tag is defined, we pass that as a filter to the API call.
        if (count($tags) < 2) {
            $queryparams['filter'] = ['tag' => (isset($tags[0]) ? $tags[0] : '')];
        }

        if ($withconfigurations) {
            $queryparams['withconfigurationpanel'] = true;
        }

        $response = $this->api->opencastapi->workflowsApi->getAllDefinitions($queryparams);
        $code = $response['code'];
        if ($code === 200) {
            $returnedworkflows = $response['body'];

            // Lookup and filter workflow definitions by tags.
            if (count($tags) > 1) {
                $returnedworkflows = array_filter($returnedworkflows, function ($wd) use ($tags) {
                    return !empty(array_intersect($wd->tags, $tags));
                });
            }

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
        } else if ($code == 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast', '', null, $code);
        }
    }

    /**
     * Retrieves a workflow definition from Opencast.
     * @param string $id Workflow definition id
     * @return false|mixed Workflow definition or false if not successful
     */
    public function get_workflow_definition($id) {
        $withoperations = false;
        $withconfigurationpanel = true;
        $response = $this->api->opencastapi->workflowsApi->getDefinition(
            $id,
            $withoperations,
            $withconfigurationpanel
        );
        if ($response['code'] === 200) {
            return $response['body'];
        }

        return false;
    }

    /**
     * Helperfunction to get the list of available workflows to be used in the plugin's settings.
     *
     * @param string $stringtags comma separated list of tags. If not empty the workflows are filtered according to this tag.
     * @param bool $withnoworkflow Add a 'no workflow' item to the list of workflows.
     *
     * @return array Returns array of OC workflows.
     *               If the list of workflows can't be retrieved from Opencast, an array with a nice error message is returned.
     */
    public function get_available_workflows_for_menu($stringtags = '', $withnoworkflow = false) {
        // Get the workflow list.
        $tags = [];
        if (!empty($stringtags)) {
            $tags = explode(',', $stringtags) ?? [];
        }
        $workflows = $this->get_existing_workflows($tags);

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
    public function can_delete_event_assignment($video, $courseid) {

        if (isset($video->processing_state) &&
            ($video->processing_state !== 'RUNNING' && $video->processing_state !== 'PAUSED')) {

            $context = context_course::instance($courseid);

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
        $workflow = get_config("block_opencast", "deleteworkflow_" . $this->ocinstanceid);

        if ($workflow) {
            if ($this->start_workflow($eventidentifier, $workflow)) {
                $record = [
                    "ocinstanceid" => $this->ocinstanceid,
                    "opencasteventid" => $eventidentifier,
                    "failed" => false,
                    "timecreated" => time(),
                    "timemodified" => time(),
                ];
                $DB->insert_record("block_opencast_deletejob", $record);
                return true;
            }
            return false;
        }
        return $this->delete_event($eventidentifier);
    }

    /**
     * Delete an event. Verify the video and check capability before.
     *
     * @param string $eventidentifier
     * @return boolean return true when video is deleted.
     */
    public function delete_event($eventidentifier) {
        $response = $this->api->opencastapi->eventsApi->delete($eventidentifier);
        if ($response['code'] >= 400) {
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
     * Get course videos for backup from all course series. This might retrieve only the videos, that
     * have a processing state of SUCCEDED.
     *
     * @param int $courseid
     * @param array $processingstates
     *
     * @return array list of videos for backup.
     */
    public function get_course_series_and_videos_for_backup($courseid, $processingstates = ['SUCCEEDED']) {
        $seriesforbackup = [];
        foreach ($this->get_course_series($courseid) as $series) {
            $result = $this->get_series_videos($series->series);

            if ($result && $result->error == 0) {
                $videosforbackup = [];
                foreach ($result->videos as $video) {
                    if (in_array($video->processing_state, $processingstates)) {
                        $videosforbackup[$video->identifier] = $video;
                    }
                }
                if ($videosforbackup) {
                    $seriesforbackup[$series->series] = $videosforbackup;
                }
            }
        }

        return $seriesforbackup;
    }

    /**
     * Check, whether the opencast system supports a given level.
     *
     * @param string $level
     * @return boolean
     */
    public function supports_api_level($level) {
        try {
            return $this->api->supports_api_level($level);
        } catch (moodle_exception $e) {
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
    public static function set_testing($testing) {
        self::$testing = $testing;
        self::get_instance(1);
    }

    // Metadata.

    /**
     * The allowance of the update metadata process
     * @param object $video Opencast video
     * @param int $courseid Course id
     * @param bool $capabilitycheck
     * @return bool the capability of updating!
     */
    public function can_update_event_metadata($video, $courseid, $capabilitycheck = true) {
        if (isset($video->processing_state) &&
            ($video->processing_state == "SUCCEEDED" || $video->processing_state == "FAILED" ||
                $video->processing_state == "PLANNED" || $video->processing_state == "STOPPED")) {
            if ($capabilitycheck) {
                $context = context_course::instance($courseid);
                return has_capability('block/opencast:addvideo', $context);
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * The allowance of using the opencast editor.
     * @param object $video Opencast video
     * @param int $courseid Course id
     * @return bool the capability of updating!
     */
    public function can_edit_event_in_editor($video, $courseid) {

        // We check if the basic editor integration configs are set, the video processing state is succeeded
        // (to avoid process failure) and there is internal publication status (to avoid error 400 in editor).
        if (get_config('block_opencast', 'enable_opencast_editor_link_' . $this->ocinstanceid) &&
            isset($video->processing_state) && in_array($video->processing_state, ["SUCCEEDED", "NEEDSCUTTING"])  &&
            isset($video->publication_status) && is_array($video->publication_status) &&
            in_array('internal', $video->publication_status)) {
            $context = context_course::instance($courseid);
            return has_capability('block/opencast:addvideo', $context);
        }

        return false;
    }

    /**
     * Get the event's metadata of the specified type
     * @param string $eventidentifier Event id
     * @param string $type metadata type
     * @return bool|int|mixed Event metadata
     */
    public function get_event_metadata($eventidentifier, $type = '') {
        $response = $this->api->opencastapi->eventsApi->getMetadata($eventidentifier, $type);
        if ($response['code'] != 200) {
            return $response['code'];
        }
        return $response['body'];
    }

    /**
     * Get the series's metadata of the specified type
     * @param string $seriesid Series id
     * @return bool|int|mixed Event metadata
     */
    public function get_series_metadata($seriesid) {
        $type = 'dublincore/series';
        $response = $this->api->opencastapi->seriesApi->getMetadata($seriesid, $type);
        if ($response['code'] != 200) {
            return $response['code'];
        }
        return $response['body'];
    }

    /**
     * Update the metadata with the matching type of the specified event.
     * @param string $eventidentifier identifier of the event
     * @param stdClass $metadata collection of metadata
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function update_event_metadata($eventidentifier, $metadata) {
        $metadata = json_encode($metadata);
        $type = 'dublincore/episode';

        $response = $this->api->opencastapi->eventsApi->updateMetadata($eventidentifier, $type, $metadata);

        if ($response['code'] == 204) {
            return $this->update_metadata($eventidentifier);
        }
        return false;
    }

    /**
     * Set the owner of a video or series.
     * @param int $courseid
     * @param string $eventidentifier Video/series identifier
     * @param int $userid User ID of the new owner
     * @param bool $isseries True if the identifier is a series
     * @return bool|int
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function set_owner($courseid, $eventidentifier, $userid, $isseries) {
        $response = null;
        if ($isseries) {
            $response = $this->api->opencastapi->seriesApi->getAcl($eventidentifier);
        } else {
            $response = $this->api->opencastapi->eventsApi->getAcl($eventidentifier);
        }
        if (empty($response) || $response['code'] != 200) {
            return false;
        }

        $jsonacl = $response['body'];

        $event = new event();
        $event->set_json_acl($jsonacl);

        $roles = json_decode(get_config('block_opencast', 'roles_' . $this->ocinstanceid));
        $ownerrole = array_search(get_config('block_opencast', 'aclownerrole_' . $this->ocinstanceid),
            array_column($roles, 'rolename'));
        $ownerrole = $roles[$ownerrole];

        // Create regex from role.
        $ownerroleregex = false;
        foreach (self::$userplaceholders as $userplaceholder) {
            $r = str_replace($userplaceholder, '.*?', $ownerrole->rolename);
            if ($r != $ownerrole->rolename) {
                $ownerroleregex = $r;
                break;
            }
        }

        if (!$ownerroleregex) {
            return false;
        }

        $ownerroleregex = '/' . $ownerroleregex . '/';

        // Remove old owner role.
        foreach ($event->get_acl() as $role) {
            if (preg_match($ownerroleregex, $role->role)) {
                $event->remove_acl($role->action, $role->role);
            }
        }

        // Add new owner role.
        $actions = array_map('trim', explode(',', $ownerrole->actions));
        foreach ($actions as $action) {
            foreach (self::replace_placeholders($ownerrole->rolename, $courseid, $eventidentifier, $userid) as $acl) {
                $event->add_acl(true, $action, $acl);
            }
        }

        $acltoupdate = $event->get_json_acl();

        // Acl roles have not changed.
        if ($acltoupdate == json_encode(array_values($jsonacl))) {
            return true;
        }

        if ($isseries) {
            $response = $this->api->opencastapi->seriesApi->updateAcl($eventidentifier, $acltoupdate);
        } else {
            $response = $this->api->opencastapi->eventsApi->updateAcl($eventidentifier, $acltoupdate);
        }

        if ($isseries) {
            return $response['code'] == 200;
        }

        if ($response['code'] == 204) {
            // Trigger workflow.
            return $this->update_metadata($eventidentifier);
        }
        return false;
    }

    /**
     * Checks if a given user is defined as owner in the passed video/series ACLs.
     * @param string[] $acls ACLs
     * @param int $userid User ID
     * @param int $courseid
     * @return bool
     */
    public function is_owner($acls, $userid, $courseid) {
        $roletosearch = self::get_owner_role_for_user($userid, $courseid);
        $acls = array_column($acls, 'role');

        return in_array($roletosearch, $acls);
    }

    /**
     * Checks if a given event/series has an owner.
     * @param string[] $acls ACLs
     * @return bool
     */
    public function has_owner($acls) {
        $ownerrole = get_config('block_opencast', 'aclownerrole_' . $this->ocinstanceid);
        $ownerroleregex = false;
        foreach (self::$userplaceholders as $userplaceholder) {
            $r = str_replace($userplaceholder, '.*?', $ownerrole);
            if ($r != $ownerrole) {
                $ownerroleregex = $r;
                break;
            }
        }

        if (!$ownerroleregex) {
            return false;
        }

        $ownerroleregex = '/' . $ownerroleregex . '/';

        foreach (array_column($acls, 'role') as $role) {
            if (preg_match($ownerroleregex, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the owner rolename for a given user.
     * @param int $userid
     * @param int $courseid
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    private function get_owner_role_for_user($userid, $courseid) {
        if (empty(get_config('block_opencast', 'aclownerrole_' . $this->ocinstanceid))) {
            return null;
        }

        $roles = json_decode(get_config('block_opencast', 'roles_' . $this->ocinstanceid));
        $ownerrole = array_search(get_config('block_opencast', 'aclownerrole_' . $this->ocinstanceid),
            array_column($roles, 'rolename'));
        $ownerrole = $roles[$ownerrole];

        $roletosearch = self::replace_placeholders($ownerrole->rolename, $courseid, null, $userid);
        return $roletosearch[0];
    }

    /**
     * Retrieves all series that are owned by the specified user.
     * @param int $userid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_series_owned_by($userid) {
        global $SITE;
        // Course id should not be used in owner role, so we can use the site id.
        $ownerrole = self::get_owner_role_for_user($userid, $SITE->id);
        if (!$ownerrole) {
            return [];
        }

        $params = [
            'withacl' => true,
            'onlyWithWriteAccess' => true,
        ];
        $response = $this->api->opencastapi->seriesApi->runWithRoles([$ownerrole])->getAll($params);
        if ($response['code'] == 200) {
            $series = $response['body'];
            return array_column($series, 'identifier');
        }
        return [];
    }

    /**
     * Retrieves all videos that are owned by the specified user.
     * @param int $userid
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_videos_owned_by($userid) {
        global $SITE;
        // Course id should not be used in owner role, so we can use the site id.
        $ownerrole = self::get_owner_role_for_user($userid, $SITE->id);

        $result = new stdClass();
        $result->videos = [];
        $result->error = 0;

        if (!$ownerrole) {
            return $result;
        }

        $params = [
            'withpublications' => true,
            'onlyWithWriteAccess' => true,
        ];
        $response = $this->api->opencastapi->eventsApi->runWithRoles([$ownerrole])->getAll($params);
        if ($response['code'] == 200) {
            if (!$videos = $response['body']) {
                return $result;
            }

            $result->videos = $videos;

            if ($result->error == 0) {
                foreach ($videos as $video) {
                    $this->extend_video_status($video);
                    $this->set_download_state($video);
                    $this->set_access_state($video);
                }
            }

            $result->videos = $videos;

            return $result;
        }

        $result->error = $response['code'];
        return $result;
    }

    /**
     * Update the metadata with the matching type of the specified series.
     * @param string $seriesid identifier of the series
     * @param array $metadata collection of metadata
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function update_series_metadata($seriesid, $metadata) {
        $response = $this->api->opencastapi->seriesApi->updateMetadata($seriesid, $metadata);
        if ($response['code'] == 200) {
            return true;
        }
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
    public function get_duplicated_episodeid($workflowid) {

        // If we don't have a number, return.
        if (!is_number($workflowid)) {
            return false;
        }

        // If the given workflow was not found, return.
        $withoperations = false;
        $withconfiguration = true;
        $response = $this->api->opencastapi->workflowsApi->get($workflowid, $withoperations, $withconfiguration);
        if ($response['code'] != 200) {
            return false;
        }

        // Return if the nothing's found.
        if (!$workflowconfiguration = $response['body']) {
            return false;
        }

        // If we are not looking at a duplication workflow at all, return.
        $duplicateworkflow = get_config('block_opencast', 'duplicateworkflow_' . $this->ocinstanceid);
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

    /**
     * Assigns the series ID to a course and applies new course ACLs to the series and events of that series.
     *
     * @param int $courseid Course ID.
     * @param string $seriesid Series ID.
     * @param int $userid User ID.
     *
     * @return stdClass $result
     */
    public function import_series_to_course_with_acl_change($courseid, $seriesid, $userid) {
        // Define result object to return.
        $result = new stdClass();
        // Assume there is no error at all.
        $result->error = 0;
        $result->seriesid = $seriesid;

        // Step 1: Update events ACL roles.

        // Get videos of series.
        $videos = $this->get_series_videos($seriesid);

        // Put events data in one place to make it simpler to use later.
        $eventsaclchangeobject = new stdClass();
        // If there are vidoes.
        if ($videos && $videos->error == 0) {
            // Defining count will help with further process of result.
            $eventsaclchangeobject->total = count($videos->videos);
            // Looping through videos.
            foreach ($videos->videos as $video) {
                // Change the ACL of the event, by using making it visible for the new course.
                $eventaclchange = $this->imported_events_acl_change($video->identifier, $courseid);

                // When there is an error, we sort them out in failed category.
                if (!$eventaclchange) {
                    // Adding video id to the failed element of the object.
                    $eventsaclchangeobject->failed[] = $video->identifier;
                    $result->error = 1;
                    continue;
                }

                // We keep record of success items too. Makes it easy for later.
                $eventsaclchangeobject->success[] = $video->identifier;
            }
        }
        // Assigning that object to the result in one go.
        $result->eventsaclchange = $eventsaclchangeobject;

        // Step 2: Update series ACL roles.
        $result->seriesaclchange = $this->imported_series_acl_change($courseid, $seriesid, $userid);

        // When the Series ACL could not be changed.
        if (!$result->seriesaclchange) {
            $result->error = 1;
        }

        // Step 3: Assign seriesid to the new course in seriesmapping.
        $result->seriesmapped = $this->map_imported_series_to_course($courseid, $seriesid);
        if (!$result->seriesmapped) {
            $result->error = 1;
        }

        // Finally return the result object, containing all the info about the whole process.
        return $result;
    }

    /**
     * Change and update the ACL of the series.
     * @param int $courseid Course ID.
     * @param string $seriesid Series ID.
     * @param int $userid User ID.
     *
     * @return bool
     */
    private function imported_series_acl_change($courseid, $seriesid, $userid) {
        // Reading acl from opencast server.
        $response = $this->api->opencastapi->seriesApi->getAcl($seriesid);
        $defaultacl = $response['body'];
        $acl = $defaultacl;
        // When the acl could not be retreived.
        if (!is_array($acl)) {
            return false;
        }

        // Get the current defined roles.
        $roles = $this->getroles();
        foreach ($roles as $role) {
            // Initialize the role object to have a better grip.
            $roleobject = new stdClass();

            foreach ($role->actions as $action) {
                // Define role object.
                $roleobject = (object)['allow' => true,
                    'role' => self::replace_placeholders($role->rolename, $courseid, null, $userid)[0],
                    'action' => $action, ];

                // Check if the role object already exists in the acl list.
                $existingacl = array_filter($acl, function ($v, $k) use ($roleobject) {
                    if ($v->role == $roleobject->role && $v->action == $roleobject->action) {
                        return true;
                    }
                }, ARRAY_FILTER_USE_BOTH);

                // In case the role object is new, we add it to the acl list. This helps making a clean list.
                if (empty($existingacl)) {
                    $acl[] = $roleobject;
                }
            }
        }

        // Put everything in params value as a string to evaluate changes.
        $defaultaclstr = json_encode(array_values($defaultacl));
        $acltoupdate = json_encode(array_values($acl));

        // When there is nothing to change.
        if ($acltoupdate == $defaultaclstr) {
            return true;
        }

        // Update the acls.
        $response = $this->api->opencastapi->seriesApi->updateAcl($seriesid, $acltoupdate);

        // Finally we return the result of that request to the server.
        return ($response['code'] == 200);
    }

    /**
     * Make Event visible for the new course that has been imported to, by changing the ACLs.
     *
     * @param string $identifier event identifier
     * @param int $courseid Course ID.
     * @return int $courseid id of the course being imported to.
     */
    private function imported_events_acl_change($identifier, $courseid) {
        // Use try to catch unwanted errors.
        try {
            // Make it visible to the course does the ACL change accordingly.
            $visibilychanged = $this->change_visibility($identifier, $courseid, block_opencast_renderer::VISIBLE);
            // In order to resolve the return result of the change_visibility method, we assume non (false) values as true.
            return ($visibilychanged !== false) ? true : false;
        } catch (moodle_exception $e) {
            // It is unlikely, but who knows.
            return false;
        }
    }

    /**
     * Map the seriesid to the course that has been imported to, by assinging the series as secondary.
     *
     * @param int $courseid Course id.
     * @param string $seriesid series id.
     */
    private function map_imported_series_to_course($courseid, $seriesid) {
        // Get the current record.
        $mapping = seriesmapping::get_record(['courseid' => $courseid,
            'series' => $seriesid, 'ocinstanceid' => $this->ocinstanceid, ], true);

        // If the mapping record does not exists, we create one.
        if (!$mapping) {
            $mapping = new seriesmapping();
            $mapping->set('ocinstanceid', $this->ocinstanceid);
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $seriesid);

            // Try to check if there is any default series for this course.
            $defaultcourseseries = seriesmapping::get_record(['ocinstanceid' => $this->ocinstanceid,
                'courseid' => $courseid, 'isdefault' => 1, ], true);
            // In case there is no default series for this course, this series will be the default.
            $isdefault = $defaultcourseseries ? 0 : 1;

            $mapping->set('isdefault', $isdefault);
            $mapping->create();
        }

        return $mapping->to_record();
    }

    /**
     * Unlinking a series from a course in 2 steps:
     * 1. Remove all related course ACLs from series event one by one.
     * 2. Remove all related course ACLs from series.
     *
     * @param int $courseid Course ID.
     * @param string $seriesid Series Identifier.
     *
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function unlink_series_from_course($courseid, $seriesid) {
        // Step 1: Remove all related course ACLs from series event one by one.
        $videos = $this->get_series_videos($seriesid);
        if ($videos->error != 0) {
            return false;
        }

        // Loop through the videos.
        $eventsaclsremoved = true;
        foreach ($videos->videos as $video) {
            if ($video->identifier) {
                $aclsremoved = $this->remove_course_acls_from_event($video->identifier, $courseid);
                if (!$aclsremoved) {
                    $eventsaclsremoved = false;
                }
            }
        }

        // Make sure all events acls are removed before we remove series acls.
        if (!$eventsaclsremoved) {
            return false;
        }

        // Step 2: Remove course ACLs from series.
        $seriesaclsremoved = $this->remove_course_acls_from_series($seriesid, $courseid);

        // Finally, we return the result of removing acls from series.
        return $seriesaclsremoved;
    }

    /**
     * Removes all course related acls from the event.
     * @param string $eventidentifier Video identifier
     * @param int $courseid
     * @return bool
     */
    private function remove_course_acls_from_event($eventidentifier, $courseid) {
        // Preparing groups.
        $groups = groupaccess::get_record(
            [
                'opencasteventid' => $eventidentifier,
                'ocinstanceid' => $this->ocinstanceid,
            ]
        );
        $groupsarray = $groups ? explode(',', $groups->get('moodlegroups')) : null;

        // Preparing all course related acls to remove.
        $aclstoremove = [];
        // Go through all roles.
        $roles = $this->getroles();
        foreach ($roles as $role) {
            // We need to check only those roles that have placeholders.
            if (preg_match("/\[(.*?)\]/", $role->rolename)) {
                // Perform replace placeholders.
                $rolenamearray = $this->replace_placeholders($role->rolename, $courseid, $groupsarray);
                if (!empty($rolenamearray)) {
                    $aclstoremove = array_merge($aclstoremove, $rolenamearray);
                }
            }
        }

        // Get event acls.
        $response = $this->api->opencastapi->eventsApi->getAcl($eventidentifier);
        $jsonacl = $response['body'];

        // Preparing dummy event to handle acls properly.
        $event = new event();
        $event->set_json_acl($jsonacl);
        // Going through current event acls to delete course acls.
        foreach ($event->get_acl() as $eventrole) {
            if (in_array($eventrole->role, $aclstoremove)) {
                $event->remove_acl($eventrole->action, $eventrole->role);
            }
        }

        // Preparing the acl params from event to set as new acls for the event.
        $acl = $event->get_json_acl();

        // Prevent further process if there is no change.
        if ($acl == json_encode(array_values($jsonacl))) {
            return true;
        }

        // Update acls.
        $response = $this->api->opencastapi->eventsApi->updateAcl($eventidentifier, $acl);

        if ($response['code'] >= 400) {
            return false;
        }

        // Update metadata.
        if ($this->update_metadata($eventidentifier)) {
            return true;
        }

        // If it hits here, means that the metadata is not updated.
        return false;
    }

    /**
     * Removes all course related acls from the series.
     * @param string $seriesid Series identifier
     * @param int $courseid
     * @return bool
     */
    private function remove_course_acls_from_series($seriesid, $courseid) {
        // Preparing all course related acls to remove.
        $aclstoremove = [];
        // Go through all roles.
        $roles = $this->getroles();
        foreach ($roles as $role) {
            // We need to check only those roles that have placeholders.
            if (preg_match("/\[(.*?)\]/", $role->rolename)) {
                // Perform replace placeholders.
                $rolenamearray = $this->replace_placeholders($role->rolename, $courseid);
                if (!empty($rolenamearray)) {
                    $aclstoremove = array_merge($aclstoremove, $rolenamearray);
                }
            }
        }

        // Get series acls.
        $response = $this->api->opencastapi->seriesApi->getAcl($seriesid);
        $jsonacl = $response['body'];
        $seriesacls = $jsonacl;

        // Make sure series ACL retreived.
        if (!is_array($seriesacls)) {
            return false;
        }

        $newacls = array_filter($seriesacls, function ($acl) use ($aclstoremove) {
            if (!in_array($acl->role, $aclstoremove)) {
                return true;
            }
        });

        $acl = json_encode(array_values($newacls));
        // Prevent further process if there is no change.
        if ($acl == json_encode(array_values($jsonacl))) {
            return true;
        }

        // Update the acls.
        $response = $this->api->opencastapi->seriesApi->updateAcl($seriesid, $acl);

        // Finally, we return boolean if series' new acls are in place.
        return ($response['code'] == 200);
    }

    /**
     * Returns lti consumer key base on ocinstance id from tool_opencast config.
     *
     * @return string|bool
     * The requested config as string or false, if the corresponding config was not found.
     *
     * @throws dml_exception
     */
    public function get_lti_consumerkey() {
        return settings_api::get_lticonsumerkey($this->ocinstanceid);
    }

    /**
     * Returns lti consumer secret base on ocinstance id from tool_opencast config.
     *
     * @return string|bool
     * The requested config as string or false, if the corresponding config was not found.
     *
     * @throws dml_exception
     */
    public function get_lti_consumersecret() {
        return settings_api::get_lticonsumersecret($this->ocinstanceid);
    }

    /**
     * Gets the event mediapackage.
     *
     * @param string $eventid event id in the opencast system.
     *
     * @return string event's mediapackage
     * @throws dml_exception
     * @throws moodle_exception
     * @throws opencast_connection_exception
     */
    public function get_event_media_package($eventid) {
        $response = $this->api->opencastrestclient->performGet("/api/episode/{$eventid}");
        $code = $response['code'];
        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }
        $mediapackage = $response['body'];
        return $mediapackage;
    }

    /**
     * The allowance of editing event's transcription
     * @param object $video Opencast video
     * @param int $courseid Course id
     * @return bool the capability of editing transcription!
     */
    public function can_edit_event_transcription($video, $courseid) {
        // To edit transcriptions, we need that the video processing to be in succeeded state to avoid any conflict in workflows.
        // We would also need to make sure that workflow for transcription is configured.
        if (!empty(get_config('block_opencast', 'transcriptionworkflow_' . $this->ocinstanceid)) &&
            isset($video->processing_state) && $video->processing_state == "SUCCEEDED") {
            $context = context_course::instance($courseid);
            return has_capability('block/opencast:addvideo', $context);
        }

        return false;
    }

    /**
     * Set the duplicated event's visiblity based on the original event's visibility.
     * @param string $duplicatedeventid duplicated event identifier
     * @param string $sourceeventid source event identifier
     * @param int $courseid target course id
     * @return bool whether the acl (visibility) is applied
     */
    public function set_duplicated_event_visibility($duplicatedeventid, $sourceeventid, $courseid) {
        // Getting the duplicated video object.
        $duplicatedvideo = $this->get_opencast_video($duplicatedeventid);
        if ($duplicatedvideo->error || !$this->can_update_event_metadata($duplicatedvideo->video, $courseid, false)) {
            return process_duplicated_event_visibility_change::TASK_PENDING;
        }
        // Getting the source video object.
        $sourcevideo = $this->get_opencast_video($sourceeventid);
        if ($sourcevideo->error || empty($sourcevideo->video->is_part_of)) {
            return process_duplicated_event_visibility_change::TASK_FAILED;
        }
        // Extracting source series.
        $sourceseries = $sourcevideo->video->is_part_of;
        // Get series mapping.
        $mapping = seriesmapping::get_record(['ocinstanceid' => $this->ocinstanceid,
            'series' => $sourceseries, 'isdefault' => '1', ]);
        // Extracting source course id to get the inital visibiltiy of the source event.
        if (!$mapping || !($sourcecourseid = $mapping->get('courseid'))) {
            return process_duplicated_event_visibility_change::TASK_FAILED;
        }

        // Now we decide the visibiltiy.
        $targetvisibiltiy = block_opencast_renderer::VISIBLE;
        $sourcevisibility = $this->is_event_visible($sourceeventid, $sourcecourseid);
        // Anything other than VISIBLE, we consider as HIDDEN.
        if ($sourcevisibility !== block_opencast_renderer::VISIBLE) {
            $targetvisibiltiy = block_opencast_renderer::HIDDEN;
        }
        // Grouping does not applying here, therefore we leave it empty.
        $groups = [];
        $event = new event();
        // Gathering acls for the duplicated event.
        $newacls = $this->get_non_permanent_acl_rules_for_status($courseid, $targetvisibiltiy, $groups);
        $newacls = array_merge($newacls, $this->get_permanent_acl_rules_for_status($courseid, $targetvisibiltiy, $groups, true));
        foreach ($newacls as $acl) {
            $event->add_acl($acl->allow, $acl->action, $acl->role);
        }

        $acl = $event->get_json_acl();
        // Update acls.
        $response = $this->api->opencastapi->eventsApi->updateAcl($duplicatedeventid, $acl);

        if ($response['code'] != 204) {
            return process_duplicated_event_visibility_change::TASK_PENDING;
        }

        // Finally, when the acls are in place we run the update metadata.
        if ($this->update_metadata($duplicatedeventid)) {
            return process_duplicated_event_visibility_change::TASK_COMPLETED;
        }

        return process_duplicated_event_visibility_change::TASK_FAILED;
    }

    /**
     * Get the opencast version.
     *
     * @return string semantic version number of the opencast server.
     */
    public function get_opencast_version() {
        $response = $this->api->opencastapi->sysinfo->getVersion();
        $code = $response['code'];
        $versionobject = $response['body'];
        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }
        return $versionobject->version;
    }

    /**
     * Adds tracks into opencast event.
     * This endpoint has been introduced in Opencast 13, it is recommended to check the opencast version before using this method.
     *
     * @param string $identifier event identifier.
     * @param string $flavor the track flavor.
     * @param object $file the track filestream object.
     * @param boolean $overwrite whether to overwrite the existing one.
     *
     * @return boolean true, if the track is added.
     * @throws opencast_connection_exception
     */
    public function event_add_track($identifier, $flavor, $file, $overwrite = true) {
        $response = $this->api->opencastapi->eventsApi->addTrack($identifier, $flavor, $file, $overwrite);
        $code = $response['code'];
        if ($code === 0) {
            throw new opencast_connection_exception('connection_failure', 'block_opencast');
        } else if ($code != 200) {
            throw new opencast_connection_exception('unexpected_api_response', 'block_opencast');
        }
        return true;
    }

    /**
     * The allowance of providing download video button
     * @param object $video Opencast video
     * @param int $courseid Course id
     * @param bool $capabilitycheck
     * @return bool whether to provide the download button
     */
    public function can_show_download_button($video, $courseid, $capabilitycheck = true) {
        // Only when the video processing is SUCCEEDED, to avoid any misunderstanding.
        if ($video->is_downloadable && isset($video->processing_state) && $video->processing_state == "SUCCEEDED") {
            if ($capabilitycheck) {
                $coursecontext = context_course::instance($courseid);
                return has_capability('block/opencast:downloadvideo', $coursecontext);
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * The allowance of providing direct access link button
     * @param object $video Opencast video
     * @param int $courseid Course id
     * @param bool $capabilitycheck
     * @return bool whether to provide the download button
     */
    public function can_show_directaccess_link($video, $courseid, $capabilitycheck = true) {
        // Only when the video processing is SUCCEEDED, to avoid any misunderstanding.
        if ($video->is_accessible && isset($video->processing_state) && $video->processing_state == "SUCCEEDED") {
            if ($capabilitycheck) {
                $coursecontext = context_course::instance($courseid);
                return has_capability('block/opencast:sharedirectaccessvideolink', $coursecontext);
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * Central place to generate studio url path and its query params if applicable.
     *
     * @param string $courseid Course ID
     * @param string $seriesid Series identifier
     * @return ?string the generated studio url path or null if something went wrong.
     */
    public function generate_studio_url_path($courseid, $seriesid) {
        global $SITE;
        // Return null if the requirements are missing.
        if (empty($courseid) || empty($seriesid)) {
            return null;
        }
        $studiourlpath = '/studio';
        $queryparams = [
            'upload.seriesId=' . $seriesid,
        ];
        // Check if Studio return button is enabled.
        if (get_config('block_opencast', 'show_opencast_studio_return_btn_' . $this->ocinstanceid)) {
            // Initializing default label for studio return button.
            $studioreturnbtnlabel = $SITE->fullname;
            // Check if custom label is configured.
            if (!empty(get_config('block_opencast', 'opencast_studio_return_btn_label_' . $this->ocinstanceid))) {
                $studioreturnbtnlabel = get_config('block_opencast', 'opencast_studio_return_btn_label_' . $this->ocinstanceid);
            }

            // Initializing default studio return url.
            $studioreturnurl = new moodle_url('/blocks/opencast/index.php',
                ['courseid' => $courseid, 'ocinstanceid' => $this->ocinstanceid]);
            // Check if custom return url is configured.
            if (!empty(get_config('block_opencast', 'opencast_studio_return_url_' . $this->ocinstanceid))) {
                // Prepare the custom url.
                $customreturnurl = get_config('block_opencast', 'opencast_studio_return_url_' . $this->ocinstanceid);
                // Slipt it into parts, to extract endpoint and query strings.
                $customreturnurlarray = explode('?', $customreturnurl);
                $customurl = $customreturnurlarray[0];
                $customquerystring = count($customreturnurlarray) > 1 ? $customreturnurlarray[1] : null;

                $customurldata = [];
                // If there is any query string.
                if (!empty($customquerystring)) {
                    // Split them.
                    $customquerystringdata = explode('&', $customquerystring);
                    // Put them into loop to replace the placeholders and add them into the customurldata array.
                    foreach ($customquerystringdata as $data) {
                        $datastring = str_replace(['[COURSEID]', '[OCINSTANCEID]'], [$courseid, $this->ocinstanceid], $data);
                        $dataarray = explode('=', $datastring);
                        if (count($dataarray) == 2) {
                            $customurldata[$dataarray[0]] = $dataarray[1];
                        }
                    }
                }

                if (!empty($customurl)) {
                    $studioreturnurl = new moodle_url($customurl, $customurldata);
                }
            }

            // Appending studio return data, only when there is a url.
            if (!empty($studioreturnurl)) {
                $queryparams[] = 'return.label=' . urlencode($studioreturnbtnlabel);
                $queryparams[] = 'return.target=' . urlencode($studioreturnurl->out(false));
            }
        }

        // Checking if custom settings filename is set.
        $customseetingsfilename = get_config('block_opencast', 'opencast_studio_custom_settings_filename_' . $this->ocinstanceid);
        if (!empty($customseetingsfilename)) {
            $queryparams[] = 'settingsFile=' . $customseetingsfilename;
        }

        // Append query params to the url path.
        if (!empty($queryparams)) {
            $studiourlpath .= '?' . implode('&', $queryparams);
        }

        // Finally we return the generate studio url path.
        return $studiourlpath;
    }
}
