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
     const TRANSCRIPTION_FLAVOR_TYPE = 'captions/vtt';

    /**
     * Saves the attachment upload job.
     *
     * @param int $uploadjobid the upload job id of the event.
     * @param string $type the attachment type.
     * @param array $attachemntfiles the array list of files to upload.
     */
    public static function save_attachment_upload_job($uploadjobid, $type, $attachemntfiles)
    {
        global $DB;
        // Create object.
        $attachments = new \stdClass();
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
            } catch (\moodle_exception $e) {
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
            } catch (\moodle_exception $e) {
                mtrace('Cleaup attachment job failed due to: ' . $e);
            }
        }
    }

    /**
     * Processes the upload attachment job.
     *
     * @param object $job represents the attachment job.
     * @throws \moodle_exception
     */
    protected function process_upload_attachment_job($job) {
        global $DB;

        // Prepare all the required variables to perform attachment upload.
        $uploadjob = $DB->get_record('block_opencast_uploadjob', ['id' => $job->uploadjobid]);
        $ocinstanceid = $uploadjob->ocinstanceid;
        $courseid = $uploadjob->courseid;
        $eventidentifier = $uploadjob->opencasteventid;
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
        $eventidentifier = $uploadjob->opencasteventid;
        if (!empty(get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid))) {
            $apibridge = apibridge::get_instance($ocinstanceid);
            $mediapackagexml = $apibridge->get_event_media_package($video->identifier);
            
            $transcriptionstoupload = json_decode($attachmentjob->files);
            foreach ($transcriptionstoupload as $transcription) {
                // Get file first.
                $fs = get_file_storage();
                $file = $fs->get_file_by_id($transcription->file_id);
                if ($file === false) {
                    mtrace('job ' . $attachmentjob->id . ':(FILE NOT FOUND) file could not be found: ' . $transcription->file_id . " ({$transcription->lang})");
                    continue;
                }
                // Prepare flavor based on the flavor code.
                $flavor = self::TRANSCRIPTION_FLAVOR_TYPE . "+{$transcription->flavor}";

                // Compile and add attachment.
                $mediapackagexml = self::perform_add_attachment($ocinstanceid, $mediapackagexml, $file, $flavor);
            }
            // Finalize the upload process.
            $transcriptionuploadworkflow = get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid);
            $success = self::perform_finalize_upload_attachment($ocinstanceid, $eventidentifier, $transcriptionuploadworkflow, $mediapackagexml);
            if ($success) {
                mtrace('job ' . $attachmentjob->id . ':(UPLOADED) Attachemtns are uploaded and workflow is started.');
                self::change_job_status($attachmentjob, self::STATUS_DONE);
            }
        } else {
            mtrace('job ' . $attachmentjob->id . ':(FAILED) no workflow is configured and the job will be deleted.');
            self::change_job_status($attachmentjob, self::STATUS_FAILED);
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
        $allowedjobstatus = array(self::STATUS_PENDING, self::STATUS_DONE,
            self::STATUS_FAILED);

        if (!in_array($status, $allowedjobstatus)) {
            throw new \coding_exception('Invalid job status code.');
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
            mtrace('job ' . $job->id . ':(DELETED) Attachemtns job and its files are deleted.');
        } else {
            mtrace('job ' . $job->id . ':(UNABLE TO DELETED) File deletion failed, cleanup postponed...');
        }
    }

    /**
     * Compiles and adds attachment using ingest.
     * Removes the existinf flavor from attachments and adds attachments via ingest.
     *
     * @param int $ocinstanceid the id of opencast instance.
     * @param string $mediapackagexml the mediapackage xml as string.
     * @param object $file the attachment file to upload.
     * @param string $flavor the attachment flavor.
     * @return string $mediapackagexml newly processed mediapackage xml as string
     */
    private static function perform_add_attachment($ocinstanceid, $mediapackagexml, $file, $flavor) {
        // Remove existing attachments with the same flavor.
        $mediapackagexml = self::remove_existing_flavor_from_mediapackage($mediapackagexml, 'type', $flavor);
        // Add attachment via ingest.
        $apibridge = apibridge::get_instance($ocinstanceid);
        $mediapackagexml = $apibridge->ingest_add_attachment($mediapackagexml, $flavor, $file);
        return  $mediapackagexml;
    }

    /**
     * Removes the reduntant attachments from mediapackage.
     *
     * @param string $mediapackagexml mediapackage xml as string.
     * @param string $attributetype the attribute type to check againts.
     * @param string $value the targeted attribute's value.
     *
     * @return string mediapackage
     */
    private static function remove_existing_flavor_from_mediapackage($mediapackagexml, $attributetype, $value)
    {
        $mediapackage = simplexml_load_string($mediapackagexml);
        $i = 0;
        $toremove = [];
        foreach ($mediapackage->attachments->attachment as $attachment) {
            if ($attachment->attributes()[$attributetype] == $value) {
                $toremove[] = $i;
            }
            $i++;
        }
        $toremove = array_reverse($toremove);
        foreach ($toremove as $i) {
            unset($mediapackage->attachments->attachment[$i]);
        }
        return $mediapackage->asXML();
    }

    /**
     * Performs the final touches after uploading attachment.
     * Takes a snapshot from mediapackage if requested and runs the configured workflow.
     *
     * @param int $ocinstanceid the id of opencast instance.
     * @param string $eventidentifier the id of the event.
     * @param string $workflow the workflow definition.
     * @param string $mediapackage the mediapackage to take the snapshot from, if not empty!
     *
     * @return boolean the result of starting workflow.
     */
    private static function perform_finalize_upload_attachment($ocinstanceid, $eventidentifier, $workflow, $mediapackage = '')
    {
        $apibridge = apibridge::get_instance($ocinstanceid);
        // If mediapackage is provided, we take a snapshot from it.
        if (!empty($mediapackage)) {
            // Take snapshot.
            $apibridge->take_snapshot($mediapackage);
        }
        // Start attachments workflow.
        return $apibridge->start_workflow($eventidentifier, $workflow);
    }

    /**
     * Deletes a single transcription from the attachments and perform ingest with configured workflow.
     *
     * @param string $ocinstanceid id of opencast instance
     * @param string $eventidentifier id of the video
     * @param string $transcriptionidentifier id of transcription
     *
     * @return boolean the result of deletion.
     */
    public static function delete_transcription($ocinstanceid, $eventidentifier, $transcriptionidentifier) {
        $success = false;
        $apibridge = apibridge::get_instance($ocinstanceid);
        $mediapackagexml = $apibridge->get_event_media_package($eventidentifier);
        $mediapackagexml = self::remove_existing_flavor_from_mediapackage($mediapackagexml, 'id', $transcriptionidentifier);
        try {
            // Take snapshot.
            $apibridge->take_snapshot($mediapackagexml);
            $ingested = $apibridge->ingest($mediapackagexml, get_config('block_opencast', 'deletetranscriptionworkflow_' . $ocinstanceid));
            if (!empty($ingested)) {
                $success = true;
            }
        } catch (\Throwable $th) {
            $success = false;
        }
        return $success;
    }

    /**
     * Uploads a single transcription file in one.
     *
     * @param object $file transcription file
     * @param string $flavorservice defined flavor service
     * @param int $ocinstanceid id of opencast instance
     * @param string $eventidentifier id of video
     *
     * @return boolean the result of starting workflow after upload 
     */
    public static function upload_single_transcription($file, $flavorservice, $ocinstanceid, $eventidentifier) {
        $apibridge = apibridge::get_instance($ocinstanceid);
        $mediapackagexml = $apibridge->get_event_media_package($eventidentifier);
        $flavor = self::TRANSCRIPTION_FLAVOR_TYPE . "+{$flavorservice}";
        // Compile and add attachment.
        $mediapackagexml = self::perform_add_attachment($ocinstanceid, $mediapackagexml, $file, $flavor);
        // Finalizing the attachment upload.
        $transcriptionuploadworkflow = get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid);
        $success = self::perform_finalize_upload_attachment($ocinstanceid, $eventidentifier, $transcriptionuploadworkflow, $mediapackagexml);
        return $success;
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
}
