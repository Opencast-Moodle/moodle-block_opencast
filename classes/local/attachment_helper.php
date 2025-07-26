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
 * Event Attachment Helper.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use block_opencast_renderer;
use coding_exception;
use moodle_exception;
use SimpleXMLElement;
use stdClass;
use Throwable;

/**
 * Event Attachment Helper.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attachment_helper {

    /** @var string File area id where attachments files are uploaded */
    const OC_FILEAREA_ATTACHMENT = 'attachmenttoupload';

    /** @var int attachment upload failed */
    const STATUS_FAILED = 0;

    /** @var int attachment upload is pendding */
    const STATUS_PENDING = 1;

    /** @var int attachment upload completed */
    const STATUS_DONE = 2;

    /** @var string transcription type of attachment */
    const ATTACHMENT_TYPE_TRANSCRIPTION = 'transcription';

    /** @var string transcription flavor */
    const TRANSCRIPTION_FLAVOR_TYPE = 'captions';

    /** @var string transcription manual subflavor */
    const TRANSCRIPTION_MANUAL_SUBFLAVOR_TYPE = 'source';

     /** @var array transcription subflavor types */
    const TRANSCRIPTION_SUBFLAVOR_TYPES = ['vtt', 'delivery', 'prepared', 'source'];

    /** @var string transcription mediatype */
    const TRANSCRIPTION_MEDIATYPE = 'text/vtt';

    /**
     * Saves the attachment upload job.
     *
     * @param int $uploadjobid the upload job id of the event.
     * @param string $type the attachment type.
     * @param array $attachemntfiles the array list of files to upload.
     */
    public static function save_attachment_upload_job($uploadjobid, $type, $attachemntfiles) {
        global $DB;
        // Create object.
        $attachments = new stdClass();
        $attachments->uploadjobid = $uploadjobid;
        $attachments->type = $type;
        $attachments->files = json_encode($attachemntfiles);
        $attachments->status = self::STATUS_PENDING;
        // Save into db.
        $DB->insert_record('block_opencast_attachments', $attachments);
    }

    /**
     * Process all attachment upload jobs.
     */
    public function cron() {
        global $DB;

        // Get the attachment upload jobs.
        $sql = "SELECT * FROM {block_opencast_attachments}" .
            " WHERE status = :status";
        $params = [];
        $params['status'] = self::STATUS_PENDING;
        $allpendingjobs = $DB->get_records_sql($sql, $params);

        if (empty($allpendingjobs)) {
            mtrace('...no attachment upload jobs to proceed');
        }

        foreach ($allpendingjobs as $job) {
            mtrace('processing: ' . $job->id);
            try {
                $this->process_upload_attachment_job($job);
            } catch (moodle_exception $e) {
                mtrace('Upload attachment job failed due to: ' . $e);
            }
        }

        // Cleanup the completed/failed jobs.
        list($insql, $inparams) = $DB->get_in_or_equal([self::STATUS_FAILED, self::STATUS_DONE], SQL_PARAMS_NAMED);
        $sql = "SELECT * FROM {block_opencast_attachments}" .
            " WHERE status {$insql}";
        $finishedjobs = $DB->get_records_sql($sql, $inparams);

        if (empty($finishedjobs)) {
            mtrace('...no attachment jobs to clean');
        }

        foreach ($finishedjobs as $job) {
            mtrace('cleaning-up: ' . $job->id);
            try {
                $this->cleanup_attachment_job($job);
            } catch (moodle_exception $e) {
                mtrace('Cleaup attachment job failed due to: ' . $e);
            }
        }
    }

    /**
     * Processes the upload attachment job.
     *
     * @param object $job represents the attachment job.
     * @throws moodle_exception
     */
    protected function process_upload_attachment_job($job) {
        global $DB;

        // Prepare all the required variables to perform attachment upload.
        $uploadjob = $DB->get_record('block_opencast_uploadjob', ['id' => $job->uploadjobid]);
        $ocinstanceid = $uploadjob->ocinstanceid;
        $courseid = $uploadjob->courseid;
        $eventidentifier = $uploadjob->opencasteventid;

        $transcriptionuploadenabled = (bool) get_config('block_opencast', 'enableuploadtranscription_' . $ocinstanceid);

        if (!$transcriptionuploadenabled) {
            mtrace('job ' . $job->id . ':(SKIPPED) Transcription upload is disabled. The upload job is postponed for later.');
            return;
        }

        $apibridge = apibridge::get_instance($ocinstanceid);

        if (empty($eventidentifier)) {
            mtrace('job ' . $job->id . ':(PENDING) video upload is not yet completed.');
            return;
        }

        $videoobject = $apibridge->get_opencast_video($eventidentifier, true);

        if ($videoobject->error) {
            mtrace('job ' . $job->id . ':(PENDING) video is still in processing by opencast.');
            return;
        }

        $video = $videoobject->video;

        // We only start uploading attachments when the video processing state is succeeded.
        if ($video->processing_state == "SUCCEEDED") {
            if ($job->type === self::ATTACHMENT_TYPE_TRANSCRIPTION) {
                mtrace('job ' . $job->id . ':(PROCESSING) Transcription attachment upload...');
                $this->upload_job_transcriptions($job, $uploadjob, $video);
            }
        } else {
            mtrace('job ' . $job->id . ':(PENDING) video is not ready yet.');
        }
    }

    /**
     * Uploads transcriptions as attachments
     *
     * @param object $attachmentjob represents the visibility job.
     * @param object $uploadjob represents the upload job.
     * @param object $video represents the video object.
     */
    protected function upload_job_transcriptions($attachmentjob, $uploadjob, $video) {
        $ocinstanceid = $uploadjob->ocinstanceid;
        $courseid = $uploadjob->courseid;
        // Upload caption transcriptions.
        $transcriptionstoupload = json_decode($attachmentjob->files);
        if (!empty($transcriptionstoupload)) {
            $storedlanguagefiles = [];
            foreach ($transcriptionstoupload as $transcription) {
                if (empty($transcription->lang)) {
                    mtrace('job ' . $attachmentjob->id . ':(NO LANG PARAM) language not set: '
                        . $transcription->file_id . " ({$transcription->lang})");
                    continue;
                }
                // Get file first.
                $fs = get_file_storage();
                $file = $fs->get_file_by_id($transcription->file_id);
                if ($file === false) {
                    mtrace('job ' . $attachmentjob->id . ':(FILE NOT FOUND) file could not be found: '
                        . $transcription->file_id . " ({$transcription->lang})");
                    continue;
                }

                $storedlanguagefiles[$transcription->lang] = $file;
            }
            $result = false;
            if (!empty($storedlanguagefiles)) {
                // Upload caption transcriptions.
                $result = self::upload_transcription_captions_set($storedlanguagefiles, $ocinstanceid, $video->identifier);
            }

            if ($result) {
                mtrace('job ' . $attachmentjob->id . ':(UPLOADED) Transcription attachments are uploaded.');
                self::change_job_status($attachmentjob, self::STATUS_DONE);
            } else {
                // We need more info about the failure, because it will be deleted directly later.
                $attachementdata = json_decode($attachmentjob->files);
                mtrace('job ' . $attachmentjob->id .
                    ':(FAILED) Transcription attachments upload failed. Info: ' . $attachementdata);
                self::change_job_status($attachmentjob, self::STATUS_FAILED);
            }
        }
    }

    /**
     * Saves the change attachment job into db.
     *
     * @param object $job attachment job object
     * @param int $status attachment status.
     */
    private static function change_job_status($job, $status) {
        global $DB;
        $allowedjobstatus = [self::STATUS_PENDING, self::STATUS_DONE,
            self::STATUS_FAILED, ];

        if (!in_array($status, $allowedjobstatus)) {
            throw new coding_exception('Invalid job status code.');
        }
        // Set the pending status.
        $job->status = $status;
        // Save into db.
        $DB->update_record('block_opencast_attachments', $job);
    }

    /**
     * Cleans up after a job is completed with removing files and the record.
     *
     * @param object $job attachment job object
     */
    protected function cleanup_attachment_job($job) {
        global $DB;
        $attachemntfiles = json_decode($job->files);
        $fs = get_file_storage();
        $filedeletionhaserror = false;
        foreach ($attachemntfiles as $attachment) {
            // Delete the file and everything related to it.
            $files = $DB->get_recordset('files', [
                'component' => 'block_opencast',
                'filearea' => self::OC_FILEAREA_ATTACHMENT,
                'itemid' => $attachment->file_itemid,
            ]);
            foreach ($files as $filer) {
                $file = $fs->get_file_instance($filer);
                if (!$file->delete()) {
                    $filedeletionhaserror = true;
                }
            }
            $files->close();
        }
        if (!$filedeletionhaserror) {
            $DB->delete_records('block_opencast_attachments', ['id' => $job->id]);
            mtrace('job ' . $job->id . ':(DELETED) Transcription upload job and its files are deleted.');
        } else {
            mtrace('job ' . $job->id . ':(UNABLE TO DELETED) File deletion failed, cleanup postponed...');
        }
    }

    /**
     * Removes media from mediapackage by id.
     *
     * @param string $mediapackagestr The mediapackage XML as a string.
     * @param string $id the media id to remove.
     *
     * @return string $mediapackage the mediapackage string.
     */
    private static function remove_media_from_mediapackage_by_id($mediapackagestr, $id) {
        $mediapackage = simplexml_load_string($mediapackagestr);
        self::remove_media_from_xml($mediapackage, 'id', $id);
        return $mediapackage->asXML();
    }

    /**
     * Removes the specified media track from the mediapackage xml object.
     *
     * @param SimpleXMLElement $mediapackage the mediapackage XML object (referenced).
     * @param string $attributetype the type of attribute to check against.
     * @param string $value the value of attribute to match with.
     *
     * @return array to remove ids.
     */
    private static function remove_media_from_xml(&$mediapackage, $attributetype, $value) {
        $i = 0;
        $toremove = [];
        $ids = [];
        foreach ($mediapackage->media->track as $item) {
            if ($item->attributes()[$attributetype] == $value) {
                $toremove[] = $i;
                $ids[] = (string) $item->attributes()['id'];
            }
            $i++;
        }
        $toremove = array_reverse($toremove);
        foreach ($toremove as $i) {
            unset($mediapackage->media->track[$i]);
        }
        return $ids;
    }

    /**
     * Deletes a single transcription track from an Opencast event and triggers the appropriate workflow.
     *
     * For Opencast 16 and above, this method removes the transcription track using the /api/events/../track endpoint.
     * For Opencast 15, it manipulates the mediapackage XML to remove the transcription and uses ingest to update the event.
     * After removal, it starts the configured deletion workflow or falls back to 'publish' if not configured.
     *
     * @param string $ocinstanceid The Opencast instance ID.
     * @param string $eventidentifier The Opencast event identifier.
     * @param stdClass $transcriptionobj The transcription publication object containing tags and flavor.
     * @return bool True if the deletion and workflow start or ingest succeeded, false otherwise.
     */
    public static function delete_transcription($ocinstanceid, $eventidentifier, $transcriptionobj) {
        $apibridge = apibridge::get_instance($ocinstanceid);
        $opencastversion = $apibridge->get_opencast_version();
        // Main support happens here, as for Opencast 16 and above the endpoint /api/events/../track can handle tags.
        if (version_compare($opencastversion, '16.0.0', '>=')) {
            $subtitletags = $transcriptionobj->tags;
            $mainmanualflavor = $transcriptionobj->flavor;
            // To remove any subtitle, we should pass null as the file.
            $apibridge->event_add_track(
                $eventidentifier,
                $mainmanualflavor,
                null,
                true,
                $subtitletags
            );
        } else {
            // This is Opencast 15 related.
            // TODO: As soon we have dropped the Opencast 15 support we can remove the following scenario of using mediapackage.
            $mediapackagestr = $apibridge->get_event_media_package($eventidentifier);

            $transcriptionidentifier = self::extract_transcription_id_from_mediapackage($mediapackagestr, $transcriptionobj);

            $mediapackagestr = self::remove_media_from_mediapackage_by_id($mediapackagestr, $transcriptionidentifier);
        }

        $deletetranscriptionworkflow = get_config('block_opencast', 'deletetranscriptionworkflow_' . $ocinstanceid);
        // We take Publish workflow as the default/fallback.
        if (empty($deletetranscriptionworkflow)) {
            $transcriptionuploadworkflow = 'publish';
        }

        // In case of Opencast 15, that we use $mediapackagestr, we should perform the ingest.
        // TODO: As soon as we dropped the support of Opencast 15 we should remove this scenario with mediapackage.
        if (isset($mediapackagestr)) {
            // Ingest the mediapackage.
            $workflow = $apibridge->ingest($mediapackagestr, $deletetranscriptionworkflow);
            return !empty($workflow);
        }

        // As for the final step we have to run the configured workflow. in order to publish the captions into engage module.
        return $apibridge->start_workflow($eventidentifier, $deletetranscriptionworkflow);
    }

    /**
     * Uploads a set of transcription caption files as media to an Opencast event.
     *
     * This method handles the upload of multiple language caption files to the specified event.
     * For Opencast 16 and above, it uses the /api/events/../track endpoint to add tracks with appropriate tags.
     * For Opencast 15, it manipulates the mediapackage XML and uses ingest to add tracks.
     * After uploading, it triggers the configured transcription workflow or falls back to 'publish'.
     *
     * @param array $storedlanguagefiles An associative array of language codes to stored_file objects.
     * @param int $ocinstanceid The Opencast instance ID.
     * @param string $eventidentifier The Opencast event identifier.
     * @return bool True if the upload and workflow start or ingest succeeded, false otherwise.
     */
    public static function upload_transcription_captions_set($storedlanguagefiles, $ocinstanceid, $eventidentifier) {
        $mainmanualflavor = self::TRANSCRIPTION_FLAVOR_TYPE . '/' . self::TRANSCRIPTION_MANUAL_SUBFLAVOR_TYPE;
        $basesubtitletags = [
            'subtitle',
            'type:subtitle',
            'generator-type:manual',
        ];
        $apibridge = apibridge::get_instance($ocinstanceid);
        $opencastversion = $apibridge->get_opencast_version();
        // Main support happens here, as for Opencast 16 and above the endpoint /api/events/../track can handle tags.
        if (version_compare($opencastversion, '16.0.0', '>=')) {
            foreach ($storedlanguagefiles as $lang => $file) {
                $subtitletags = $basesubtitletags;
                $subtitletags[] = "lang:$lang";
                $filestream = $apibridge->get_upload_filestream($file, 'file');
                $apibridge->event_add_track(
                    $eventidentifier,
                    $mainmanualflavor,
                    $filestream,
                    true,
                    $subtitletags
                );
            }
        } else {
            // This is Opencast 15 related.
            // TODO: As soon we have dropped the Opencast 15 support we can remove the following scenario of using mediapackage.
            $mediapackagestr = $apibridge->get_event_media_package($eventidentifier);
            foreach ($storedlanguagefiles as $lang => $file) {
                $subtitletags = $basesubtitletags;
                $subtitletags[] = "lang:$lang";
                $filestream = $apibridge->get_upload_filestream($file, 'file');
                $mediapackagestr = self::removing_existing_transcription_in_mediapackage(
                    $mediapackagestr,
                    $mainmanualflavor,
                    $subtitletags
                );
                // Now that the existing are removed, we perform the add track via ingest.
                $mediapackagestr = $apibridge->ingest_add_track($mediapackagestr, $mainmanualflavor, $filestream, $subtitletags);
            }
        }

        // After that, we need to make sure that the uploaded captions are going to get processed by starting the workflow.
        $transcriptionuploadworkflow = get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid);
        // We take Publish workflow as the default/fallback.
        if (empty($transcriptionuploadworkflow)) {
            $transcriptionuploadworkflow = 'publish';
        }

        // In case of Opencast 15, that we use $mediapackagestr, we should perform the ingest.
        // TODO: As soon as we dropped the support of Opencast 15 we should remove this scenario with mediapackage.
        if (isset($mediapackagestr)) {
            // Ingest the mediapackage.
            $workflow = $apibridge->ingest($mediapackagestr, $transcriptionuploadworkflow);
            return !empty($workflow);
        }

        // As for the final step we have to run the configured workflow. in order to publish the captions into engage module.
        return $apibridge->start_workflow($eventidentifier, $transcriptionuploadworkflow);
    }

    /**
     * Removes an existing transcription track from the mediapackage XML string based on flavor and tags.
     *
     * This method searches the mediapackage for a transcription track matching the specified flavor and tags,
     * and removes it if found. It is primarily used to ensure that duplicate or outdated transcriptions are not present
     * before adding a new one, especially when working with Opencast 15 ingest workflows.
     *
     * @param string $mediapackagestr The mediapackage XML as a string.
     * @param string $flavor The flavor string to match (e.g., 'captions/source').
     * @param array $tags An array of tags to match against the transcription track.
     * @return string The updated mediapackage XML string with the matching transcription removed, if found.
     *
     * @todo This method should be removed once support for Opencast 15 is dropped.
     */
    private static function removing_existing_transcription_in_mediapackage($mediapackagestr, $flavor, $tags) {
        $dummymediaobj = new \stdClass();
        // In order to find this we have to add "archive" tag,
        // because every processed caption gets it at the end.
        $tags[] = 'archive';
        $dummymediaobj->tags = $tags;
        $dummymediaobj->flavor = $flavor;
        $targetedmediaid = self::extract_transcription_id_from_mediapackage($mediapackagestr, $dummymediaobj);
        if (!empty($targetedmediaid)) {
            $mediapackagestr = self::remove_media_from_mediapackage_by_id($mediapackagestr, $targetedmediaid);
        }

        return $mediapackagestr;
    }

    /**
     * Removes the transcription file after the single upload is completed no matter what the status is.
     *
     * @param int $fileitemid transcription file item id
     */
    public static function remove_single_transcription_file($fileitemid) {
        global $DB;
        // Delete the file and everything related to it.
        $files = $DB->get_recordset('files', [
            'component' => 'block_opencast',
            'filearea' => self::OC_FILEAREA_ATTACHMENT,
            'itemid' => $fileitemid,
        ]);
        $fs = get_file_storage();
        foreach ($files as $filer) {
            $file = $fs->get_file_instance($filer);
            $file->delete();
        }
        $files->close();
    }

    /**
     * Gets the mediapackage id based on comparing the actual publication object.
     *
     * @param string $mediapackagestr the event mediapackage.
     * @param object $transcriptionobj the transcription publication object.
     * @param string $pubtype the publication type, either media or attachements.
     *
     * @return string|null the medispackage id or null if it could not be found.
     */
    public static function extract_transcription_id_from_mediapackage($mediapackagestr, $transcriptionobj, $pubtype = 'media') {
        $mediapackagexml = simplexml_load_string($mediapackagestr);
        $pubsubtype = $pubtype == 'media' ? 'track' : 'attachment';
        if (property_exists($mediapackagexml, $pubtype)) {
            foreach ($mediapackagexml->$pubtype->$pubsubtype as $item) {
                $itemobj = json_decode(json_encode((array) $item));
                $mathcingmimetype = true;
                if (isset($transcriptionobj->mediatype)) {
                    $mathcingmimetype = $itemobj->mimetype == $transcriptionobj->mediatype;
                }
                $matchingflavor = $itemobj->{'@attributes'}->type == $transcriptionobj->flavor;
                if ($mathcingmimetype && $matchingflavor) {
                    // As of Opencast 15, subtitles are all about tags, therefore we need to go through tags one by one.
                    if (property_exists($itemobj, 'tags')) {
                        $itemtags = $itemobj->tags->tag;
                        if (!is_array($itemtags)) {
                            $itemtags = [$itemtags];
                        }
                        if (count($transcriptionobj->tags) === count($itemtags)) {
                            $alltagsmatch = true;
                            foreach ($itemtags as $tag) {
                                if (!in_array($tag, $transcriptionobj->tags)) {
                                    $alltagsmatch = false;
                                }
                            }
                            if ($alltagsmatch) {
                                return $itemobj->{'@attributes'}->id;
                            }
                        }
                    }
                }
            }
        }
        return null;
    }
}
