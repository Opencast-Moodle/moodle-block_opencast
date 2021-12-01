<?php

namespace block_opencast\local;

use block_opencast\opencast_connection_exception;
use block_opencast\opencast_state_exception;
use local_chunkupload\local\chunkupload_file;
use tool_opencast\local\PolyfillCURLStringFile;

class ingest_uploader
{
    const STATUS_INGEST_CREATING_MEDIA_PACKAGE = 221;

    const STATUS_INGEST_ADDING_EPISODE_CATALOG = 222;

    const STATUS_INGEST_ADDING_FIRST_TRACK = 223;

    const STATUS_INGEST_ADDING_SECOND_TRACK = 224;

    const STATUS_INGEST_ADDING_ACL_ATTACHMENT = 225;

    const STATUS_INGEST_INGESTING = 226;

    public static function create_event($job) {
        global $DB;
        $apibridge = apibridge::get_instance($job->ocinstanceid);

        switch ($job->status) {
            case self::STATUS_INGEST_CREATING_MEDIA_PACKAGE:
                try {
                    $mediapackage = $apibridge->ingest_create_media_package();
                    mtrace('... media package created');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_EPISODE_CATALOG, true, false, false, $mediapackage);
                } catch (opencast_connection_exception $e) {
                    mtrace('... failed to create media package');
                    mtrace($e->getMessage());
                    break;
                }
            case self::STATUS_INGEST_ADDING_EPISODE_CATALOG:
                try {
                    upload_helper::ensure_series_metadata($job, $apibridge);
                    $episodexml = self::create_episode_xml($job);

                    if (version_compare(phpversion(), '8', '>=')) {
                        $file = new \CURLStringFile($episodexml, 'dublincore-episode.xml', 'text/xml',);
                    } else {
                        $file = new PolyfillCURLStringFile($episodexml, 'dublincore-episode.xml', 'text/xml',);
                    }

                    $mediapackage = $apibridge->ingest_add_catalog($job->mediapackage, 'dublincore/episode', $file);
                    mtrace('... added episode metadata');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_FIRST_TRACK, true, false, false, $mediapackage);

                } catch (opencast_connection_exception $e) {
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
                        throw new \moodle_exception("local_chunkupload is not installed. This should never happen.");
                    }
                    $presenter = new chunkupload_file($job->chunkupload_presenter);
                    if (!$presenter) {
                        $validstoredfile = false;
                    }
                }

                if ($validstoredfile && !$presenter) {
                    // No file to upload.
                    mtrace('... no presenter to upload');
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_SECOND_TRACK, true, false, false, $job->mediapackage);
                } else if (!$validstoredfile) {
                    $DB->delete_records('block_opencast_uploadjob', ['id' => $job->id]);
                    throw new \moodle_exception('invalidfiletoupload', 'tool_opencast');
                } else {
                    try {
                        $mediapackage = $apibridge->ingest_add_track($job->mediapackage, 'presenter/source', $presenter);
                        mtrace('... presenter uploaded');
                        self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_SECOND_TRACK, true, false, false, $mediapackage);

                    } catch (opencast_connection_exception $e) {
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
                        throw new \moodle_exception("local_chunkupload is not installed. This should never happen.");
                    }
                    $presentation = new chunkupload_file($job->chunkupload_presentation);
                    if (!$presentation) {
                        $validstoredfile = false;
                    }
                }

                if ($validstoredfile && !$presentation) {
                    // No file to upload.
                    mtrace('... no presentation to upload');
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_ACL_ATTACHMENT, true, false, false, $job->mediapackage);
                } else if (!$validstoredfile) {
                    $DB->delete_records('block_opencast_uploadjob', ['id' => $job->id]);
                    throw new \moodle_exception('invalidfiletoupload', 'tool_opencast');
                } else {
                    try {
                        $mediapackage = $apibridge->ingest_add_track($job->mediapackage, 'presentation/source', $presentation);
                        mtrace('... presentation uploaded');
                        self::update_status_with_mediapackage($job, self::STATUS_INGEST_ADDING_ACL_ATTACHMENT, true, false, false, $mediapackage);

                    } catch (opencast_connection_exception $e) {
                        mtrace('... failed upload presentation');
                        mtrace($e->getMessage());
                        break;
                    }
                }

            case self::STATUS_INGEST_ADDING_ACL_ATTACHMENT:
                try {

                    $aclxml = self::create_acl_xml($apibridge->getroles(), $job);

                    if (version_compare(phpversion(), '8', '>=')) {
                        $file = new \CURLStringFile($aclxml, 'xacml-episode.xml', 'text/xml',);
                    } else {
                        $file = new PolyfillCURLStringFile($aclxml, 'xacml-episode.xml', 'text/xml',);
                    }

                    $mediapackage = $apibridge->ingest_add_attachment($job->mediapackage, 'ecurity/xacml+episode', $file);
                    mtrace('... added acl');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, self::STATUS_INGEST_INGESTING, true, false, false, $mediapackage);

                } catch (opencast_connection_exception $e) {
                    mtrace('... failed to add acl');
                    mtrace($e->getMessage());
                    break;
                }
            case self::STATUS_INGEST_INGESTING:
                try {
                    $workflow = $apibridge->ingest($job->mediapackage);
                    mtrace('... video uploaded');
                    // Move on to next status.
                    self::update_status_with_mediapackage($job, upload_helper::STATUS_UPLOADED);

                    $parser = xml_parser_create();
                    xml_parse_into_struct($parser, $workflow, $values);
                    xml_parser_free($parser);

                    $event = new \stdClass();
                    $event->identifier = $values[array_search('MP:MEDIAPACKAGE', array_column($values, 'tag'))]['attributes']['ID'];

                    return $event;
                } catch (opencast_connection_exception $e) {
                    mtrace('... failed to add acl');
                    mtrace($e->getMessage());
                    break;
                }
        }
        return false;
    }

    protected static function create_episode_xml($job) {

        $dom = new \DOMDocument('1.0', 'utf-8');

        $root = $dom->createElement('dublincore');
        $root->setAttributeNode(new \DOMAttr('xmlns', 'http://www.opencastproject.org/xsd/1.0/dublincore/'));
        $root->setAttributeNode(new \DOMAttr('xmlns:dcterms', 'http://purl.org/dc/terms/'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));
        $dom->appendChild($root);

        $startDate = null;
        $startTime = null;

        foreach (json_decode($job->metadata) as $metadata) {
            if ($metadata->id === 'startDate') {
                $startDate = $metadata->value;
                continue;
            } else if ($metadata->id === 'startTime') {
                $startTime = $metadata->value;
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

        if ($startDate && $startTime) {
            $date = new \DateTime($startDate . ' ' . $startTime);
            $start_end_string_iso = $date->format('Y-m-d\TH:i:s.u\Z');
            $el = $dom->createElement('dcterms:temporal', 'start=' . $start_end_string_iso . '; ' . 'end=' . $start_end_string_iso . '; scheme=W3C-DTF;');
            $el->setAttributeNode(new \DOMAttr('xsi:type', 'dcterms:Period'));
            $root->appendChild($el);
        }

        $el = $dom->createElement('dcterms:created', (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'));
        $root->appendChild($el);

        return $dom->saveXml();
    }

    protected static function create_acl_xml($roles, $job) {


        $dom = new \DOMDocument('1.0', 'utf-8');
        $root = $dom->createElement('Policy');
        $root->setAttributeNode(new \DOMAttr('PolicyId', 'mediapackage-1'));
        $root->setAttributeNode(new \DOMAttr('Version', '2.0'));
        $root->setAttributeNode(new \DOMAttr('RuleCombiningAlgId', 'urn:oasis:names:tc:xacml:1.0:rule-combining-algorithm:permit-overrides'));
        $root->setAttributeNode(new \DOMAttr('xmlns', 'urn:oasis:names:tc:xacml:2.0:policy:schema:os'));
        $dom->appendChild($root);

        foreach ($roles as $role) {
            foreach ($role->actions as $roleaction) {
                $rolename = apibridge::replace_placeholders($role->rolename, $job->courseid, null, $job->userid)[0];

                $el = $dom->createElement('RULE');
                $el->setAttributeNode(new \DOMAttr('RuleId', $rolename . '_' . $roleaction . '_PERMIT'));
                $el->setAttributeNode(new \DOMAttr('Effect', 'Permit'));
                $root->appendChild($el);

                $target = $dom->createElement('Target');
                $el->appendChild($target);

                $actions = $dom->createElement('Actions');
                $target->appendChild($actions);

                $action = $dom->createElement('Action');
                $actions->appendChild($action);

                $actionmatch = $dom->createElement('ActionMatch');
                $actionmatch->setAttributeNode(new \DOMAttr('MatchId', 'urn:oasis:names:tc:xacml:1.0:function:string-equal'));
                $action->appendChild($actionmatch);

                $attributevalue = $dom->createElement('AttributeValue', $roleaction);
                $attributevalue->setAttributeNode(new \DOMAttr('DataType', 'http://www.w3.org/2001/XMLSchema#string'));
                $actionmatch->appendChild($attributevalue);

                $actionattributedesignator = $dom->createElement('ActionAttributeDesignator');
                $actionattributedesignator->setAttributeNode(new \DOMAttr('AttributeId', 'urn:oasis:names:tc:xacml:1.0:action:action-id'));
                $actionattributedesignator->setAttributeNode(new \DOMAttr('DataType', 'http://www.w3.org/2001/XMLSchema#string'));
                $actionmatch->appendChild($actionattributedesignator);

                $condition = $dom->createElement('Condition');
                $el->appendChild($condition);

                $apply = $dom->createElement('Apply');
                $apply->setAttributeNode(new \DOMAttr('FunctionId', 'urn:oasis:names:tc:xacml:1.0:function:string-is-in'));
                $condition->appendChild($apply);

                $attributevalue = $dom->createElement('AttributeValue', $rolename);
                $attributevalue->setAttributeNode(new \DOMAttr('DataType', 'http://www.w3.org/2001/XMLSchema#string'));
                $apply->appendChild($attributevalue);

                $subjectattributedesignator = $dom->createElement('SubjectAttributeDesignator');
                $subjectattributedesignator->setAttributeNode(new \DOMAttr('AttributeId', 'urn:oasis:names:tc:xacml:2.0:subject:role'));
                $subjectattributedesignator->setAttributeNode(new \DOMAttr('DataType', 'http://www.w3.org/2001/XMLSchema#string'));
                $apply->appendChild($subjectattributedesignator);
            }
        }

        // Add deny rule.
        $el = $dom->createElement('RULE');
        $el->setAttributeNode(new \DOMAttr('RuleId', 'DenyRule'));
        $el->setAttributeNode(new \DOMAttr('Effect', 'Deny'));
        $root->appendChild($el);

        return $dom->saveXml();
    }

    public static function update_status_with_mediapackage(&$job, $status, $setmodified = true, $setstarted = false, $setsucceeded = false, $mediapackage = null) {
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

    // TODO randomize ingest endpoint
}