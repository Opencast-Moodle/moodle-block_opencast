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
 * Uploads videos via ingest nodes.
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use tool_opencast\exception\opencast_api_response_exception;
use block_opencast\opencast_state_exception;
use coding_exception;
use DateTime;
use DateTimeZone;
use dml_exception;
use DOMAttr;
use DOMDocument;
use Exception;
use lang_string;
use local_chunkupload\local\chunkupload_file;
use moodle_exception;
use SimpleXMLElement;
use stdClass;

/**
 * Uploads videos via ingest nodes.
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ingest_uploader {

    /** @var int Media package is created */
    const STATUS_INGEST_CREATING_MEDIA_PACKAGE = 221;

    /** @var int Episode metadata is added */
    const STATUS_INGEST_ADDING_EPISODE_CATALOG = 222;

    /** @var int First track (presenter) is added */
    const STATUS_INGEST_ADDING_FIRST_TRACK = 223;

    /** @var int Second track (presentation) is added */
    const STATUS_INGEST_ADDING_SECOND_TRACK = 224;

    /** @var int ACL metadata is added */
    const STATUS_INGEST_ADDING_ACL_ATTACHMENT = 225;

    /** @var int Video (final media package) is ingested */
    const STATUS_INGEST_INGESTING = 226;

    /**
     * Processes the different steps of creating an event via ingest nodes.
     * @param object $job Represents the upload job.
     * @return false|stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function create_event($job) {
        global $DB;
        $apibridge = apibridge::get_instance($job->ocinstanceid);
        $wfconfighelper = workflowconfiguration_helper::get_instance($job->ocinstanceid);

        switch ($job->status) {
            case self::STATUS_INGEST_CREATING_MEDIA_PACKAGE:
                try {
                    $mediapackage = $apibridge->ingest_create_media_package();
                    mtrace('... media package created');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_EPISODE_CATALOG,
                        true, false, false, $mediapackage);
                } catch (opencast_api_response_exception $e) {
                    mtrace('... failed to create media package');
                    mtrace($e->getMessage());
                    break;
                }
            case self::STATUS_INGEST_ADDING_EPISODE_CATALOG:
                try {
                    upload_helper::ensure_series_metadata($job, $apibridge);
                    $episodexml = self::create_episode_xml($job);

                    $file = $apibridge->get_upload_xml_file('dublincore-episode.xml', $episodexml);

                    $mediapackage = $apibridge->ingest_add_catalog($job->mediapackage, 'dublincore/episode', $file);
                    mtrace('... added episode metadata');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_FIRST_TRACK,
                        true, false, false, $mediapackage);

                } catch (opencast_api_response_exception $e) {
                    mtrace('... failed to add episode metadata');
                    mtrace($e->getMessage());
                    break;
                }

            case self::STATUS_INGEST_ADDING_FIRST_TRACK:

                $validstoredfile = true;
                $presenter = null;
                if ($job->presenter_fileid) {
                    $fs = get_file_storage();
                    $presenter = $fs->get_file_by_id($job->presenter_fileid);
                    if (!$presenter) {
                        $validstoredfile = false;
                    }
                }

                if ($job->chunkupload_presenter) {
                    if (!class_exists('\local_chunkupload\chunkupload_form_element')) {
                        throw new moodle_exception("local_chunkupload is not installed. This should never happen.");
                    }
                    $presenter = new chunkupload_file($job->chunkupload_presenter);
                    if (!$presenter) {
                        $validstoredfile = false;
                    }
                }

                if ($validstoredfile && !$presenter) {
                    // No file to upload.
                    mtrace('... no presenter to upload');
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_SECOND_TRACK,
                        true, false, false, $job->mediapackage);
                } else if (!$validstoredfile) {
                    $DB->delete_records('block_opencast_uploadjob', ['id' => $job->id]);
                    throw new moodle_exception('invalidfiletoupload', 'tool_opencast');
                } else {
                    try {
                        $mediapackage = $apibridge->ingest_add_track($job->mediapackage, 'presenter/source', $presenter);
                        mtrace('... presenter uploaded');
                        self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_SECOND_TRACK,
                            true, false, false, $mediapackage);

                    } catch (opencast_api_response_exception $e) {
                        mtrace('... failed upload presenter');
                        mtrace($e->getMessage());
                        break;
                    }
                }

            case self::STATUS_INGEST_ADDING_SECOND_TRACK:

                $validstoredfile = true;
                $presentation = null;
                if ($job->presentation_fileid) {
                    $fs = get_file_storage();
                    $presentation = $fs->get_file_by_id($job->presentation_fileid);
                    if (!$presentation) {
                        $validstoredfile = false;
                    }
                }

                if ($job->chunkupload_presentation) {
                    if (!class_exists('\local_chunkupload\chunkupload_form_element')) {
                        throw new moodle_exception("local_chunkupload is not installed. This should never happen.");
                    }
                    $presentation = new chunkupload_file($job->chunkupload_presentation);
                    if (!$presentation) {
                        $validstoredfile = false;
                    }
                }

                if ($validstoredfile && !$presentation) {
                    // No file to upload.
                    mtrace('... no presentation to upload');
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_ACL_ATTACHMENT,
                        true, false, false, $job->mediapackage);
                } else if (!$validstoredfile) {
                    $DB->delete_records('block_opencast_uploadjob', ['id' => $job->id]);
                    throw new moodle_exception('invalidfiletoupload', 'tool_opencast');
                } else {
                    try {
                        $mediapackage = $apibridge->ingest_add_track($job->mediapackage, 'presentation/source', $presentation);
                        mtrace('... presentation uploaded');
                        self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_ACL_ATTACHMENT,
                            true, false, false, $mediapackage);

                    } catch (opencast_api_response_exception $e) {
                        mtrace('... failed upload presentation');
                        mtrace($e->getMessage());
                        break;
                    }
                }

            case self::STATUS_INGEST_ADDING_ACL_ATTACHMENT:
                try {

                    $initialvisibility = visibility_helper::get_initial_visibility($job);
                    $aclxml = self::create_acl_xml($initialvisibility->roles, $job);

                    $file = $apibridge->get_upload_xml_file('xacml-episode.xml', $aclxml);

                    $mediapackage = $apibridge->ingest_add_attachment($job->mediapackage, 'security/xacml+episode', $file);
                    mtrace('... added acl');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_INGESTING,
                        true, false, false, $mediapackage);

                } catch (opencast_api_response_exception $e) {
                    mtrace('... failed to add acl');
                    mtrace($e->getMessage());
                    break;
                }
            case self::STATUS_INGEST_INGESTING:
                try {
                    // Prepare workflow configuration beforehand.
                    $processingdata = $wfconfighelper->get_workflow_processing_data($job->workflowconfiguration);
                    $workflowconfiguration = $processingdata['configuration'];
                    $workflow = $apibridge->ingest($job->mediapackage, '', $workflowconfiguration);
                    mtrace('... video uploaded');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, upload_helper::STATUS_UPLOADED);

                    $parser = xml_parser_create();
                    xml_parse_into_struct($parser, $workflow, $values);
                    xml_parser_free($parser);

                    $event = new stdClass();
                    $event->identifier = $values[array_search('MP:MEDIAPACKAGE',
                        array_column($values, 'tag'))]['attributes']['ID'];
                    $event->workflowid = $values[array_search('MP:WORKFLOW',
                        array_column($values, 'tag'))]['attributes']['ID'];

                    return $event;
                } catch (opencast_api_response_exception $e) {
                    mtrace('... failed to add acl');
                    mtrace($e->getMessage());
                    break;
                }
        }
        return false;
    }

    /**
     * Transforms the episode metadata to the dublincore/episode xml format.
     * @param object $job
     * @return false|string
     * @throws Exception
     */
    protected static function create_episode_xml($job) {

        $dom = new DOMDocument('1.0', 'utf-8');

        $root = $dom->createElement('dublincore');
        $root->setAttributeNode(new DOMAttr('xmlns', 'http://www.opencastproject.org/xsd/1.0/dublincore/'));
        $root->setAttributeNode(new DOMAttr('xmlns:dcterms', 'http://purl.org/dc/terms/'));
        $root->setAttributeNode(new DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));
        $dom->appendChild($root);

        $startdate = null;
        $starttime = null;

        foreach (json_decode($job->metadata) as $metadata) {
            if ($metadata->id === 'startDate') {
                $startdate = $metadata->value;
                continue;
            } else if ($metadata->id === 'startTime') {
                $starttime = $metadata->value;
                continue;
            } else {
                if ($metadata->id === 'subjects') {
                    $metadata->id = 'subject';
                } else if ($metadata->id === 'location') {
                    $metadata->id = 'spatial';
                }

                if (is_array($metadata->value)) {
                    $el = $dom->createElement('dcterms:' . $metadata->id, implode(',', $metadata->value));
                } else {
                    $el = $dom->createElement('dcterms:' . $metadata->id, $metadata->value);
                }
            }
            $root->appendChild($el);
        }

        if ($startdate && $starttime) {
            $date = new DateTime($startdate . ' ' . $starttime);
            $startiso = $date->format('Y-m-d\TH:i:s.u\Z');
            $el = $dom->createElement('dcterms:temporal', 'start=' . $startiso . '; ' .
                'end=' . $startiso . '; scheme=W3C-DTF;');
            $el->setAttributeNode(new DOMAttr('xsi:type', 'dcterms:Period'));
            $root->appendChild($el);
        }

        $el = $dom->createElement('dcterms:created', (new DateTime('now',
            new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'));
        $root->appendChild($el);

        return $dom->saveXml();
    }

    /**
     * Transforms the ACL to the security/xacml+episode xml format.
     * @param array $roles
     * @param object $job
     * @return false|string
     * @throws coding_exception
     * @throws dml_exception
     */
    protected static function create_acl_xml($roles, $job) {
        $mediapackageid = 'mediapackage-1';
        if (!empty($job->mediapackage)) {
            $mediapackagexml = new SimpleXMLElement($job->mediapackage);
            $mediapackageid = (string)$mediapackagexml['id'];
        }
        $dom = new DOMDocument('1.0', 'utf-8');
        $root = $dom->createElement('Policy');
        $root->setAttributeNode(new DOMAttr('PolicyId', $mediapackageid));
        $root->setAttributeNode(new DOMAttr('Version', '2.0'));
        $root->setAttributeNode(new DOMAttr('RuleCombiningAlgId',
            'urn:oasis:names:tc:xacml:1.0:rule-combining-algorithm:permit-overrides'));
        $root->setAttributeNode(new DOMAttr('xmlns', 'urn:oasis:names:tc:xacml:2.0:policy:schema:os'));
        $dom->appendChild($root);

        foreach ($roles as $acl) {
            $rolename = $acl->role;
            $roleaction = $acl->action;

            $el = $dom->createElement('Rule');
            $el->setAttributeNode(new DOMAttr('RuleId', $rolename . '_' . $roleaction . '_PERMIT'));
            $el->setAttributeNode(new DOMAttr('Effect', 'Permit'));
            $root->appendChild($el);

            $target = $dom->createElement('Target');
            $el->appendChild($target);

            $actions = $dom->createElement('Actions');
            $target->appendChild($actions);

            $action = $dom->createElement('Action');
            $actions->appendChild($action);

            $actionmatch = $dom->createElement('ActionMatch');
            $actionmatch->setAttributeNode(new DOMAttr('MatchId', 'urn:oasis:names:tc:xacml:1.0:function:string-equal'));
            $action->appendChild($actionmatch);

            $attributevalue = $dom->createElement('AttributeValue', $roleaction);
            $attributevalue->setAttributeNode(new DOMAttr('DataType', 'http://www.w3.org/2001/XMLSchema#string'));
            $actionmatch->appendChild($attributevalue);

            $actionattributedesignator = $dom->createElement('ActionAttributeDesignator');
            $actionattributedesignator->setAttributeNode(new DOMAttr('AttributeId',
                'urn:oasis:names:tc:xacml:1.0:action:action-id'));
            $actionattributedesignator->setAttributeNode(new DOMAttr('DataType',
                'http://www.w3.org/2001/XMLSchema#string'));
            $actionmatch->appendChild($actionattributedesignator);

            $condition = $dom->createElement('Condition');
            $el->appendChild($condition);

            $apply = $dom->createElement('Apply');
            $apply->setAttributeNode(new DOMAttr('FunctionId',
                'urn:oasis:names:tc:xacml:1.0:function:string-is-in'));
            $condition->appendChild($apply);

            $attributevalue = $dom->createElement('AttributeValue', $rolename);
            $attributevalue->setAttributeNode(new DOMAttr('DataType', 'http://www.w3.org/2001/XMLSchema#string'));
            $apply->appendChild($attributevalue);

            $subjectattributedesignator = $dom->createElement('SubjectAttributeDesignator');
            $subjectattributedesignator->setAttributeNode(new DOMAttr('AttributeId',
                'urn:oasis:names:tc:xacml:2.0:subject:role'));
            $subjectattributedesignator->setAttributeNode(new DOMAttr('DataType',
                'http://www.w3.org/2001/XMLSchema#string'));
            $apply->appendChild($subjectattributedesignator);
        }

        // Add deny rule.
        $el = $dom->createElement('Rule');
        $el->setAttributeNode(new DOMAttr('RuleId', 'DenyRule'));
        $el->setAttributeNode(new DOMAttr('Effect', 'Deny'));
        $root->appendChild($el);

        return $dom->saveXml();
    }

    /**
     * Update the status of the upload job.
     * @param object $job
     * @param int $status
     * @param bool $setmodified
     * @param false $setstarted
     * @param false $setsucceeded
     * @param null $mediapackage
     * @throws dml_exception
     */
    public static function update_status_with_mediapackage(&$job, $status, $setmodified = true, $setstarted = false,
                                                           $setsucceeded = false, $mediapackage = null) {
        global $DB;
        $time = time();
        if ($setstarted) {
            $job->timestarted = $time;
        }
        if ($setmodified) {
            $job->timemodified = $time;
        }
        if ($setsucceeded) {
            $job->timesucceeded = $time;
        }
        if ($mediapackage) {
            $job->mediapackage = $mediapackage;
        }

        $job->status = $status;

        $DB->update_record('block_opencast_uploadjob', $job);
    }

    /**
     * Get explaination string for ingest status code
     * @param int $statuscode Status code
     * @return lang_string|string Name of status code or empty if not found.
     */
    public static function get_status_string($statuscode) {
        switch ($statuscode) {
            case self::STATUS_INGEST_CREATING_MEDIA_PACKAGE :
                return get_string('ingeststatecreatingmedispackage', 'block_opencast');
            case self::STATUS_INGEST_ADDING_EPISODE_CATALOG :
                return get_string('ingeststateaddingcatalog', 'block_opencast');
            case self::STATUS_INGEST_ADDING_FIRST_TRACK :
                return get_string('ingeststateaddingfirsttrack', 'block_opencast');
            case self::STATUS_INGEST_ADDING_SECOND_TRACK :
                return get_string('ingeststateaddingsecondtrack', 'block_opencast');
            case self::STATUS_INGEST_ADDING_ACL_ATTACHMENT :
                return get_string('ingeststateaddingacls', 'block_opencast');
            case self::STATUS_INGEST_INGESTING :
                return get_string('ingeststateingesting', 'block_opencast');
            default :
                '';
        }
    }
}
