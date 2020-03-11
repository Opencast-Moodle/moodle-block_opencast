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
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/filelib.php');

use block_opencast\opencast_state_exception;

class upload_helper {

    const OC_FILEAREA_VIDEO = 'videotoupload';
    const OC_FILEAREA_ATTACHMENT = 'attachmenttoupload';
    const STATUS_READY_TO_UPLOAD = 10;
    const STATUS_CREATING_GROUP = 21;
    const STATUS_CREATING_SERIES = 22;
    const STATUS_CHECK_DUPLICATE = 25;
    const STATUS_CREATING_MEDIA_PACKAGE = 26;
    const STATUS_UPLOADING_METADATA = 27;
    const STATUS_UPLOADING_ACL = 28;
    const STATUS_UPLOADING_PRESENTER = 29;
    const STATUS_UPLOADING_PRESENTATION = 30;
    const STATUS_UPLOADING_ATTACHMENTS = 31;
    const STATUS_INGESTING = 32;
    const STATUS_UPLOADED = 35;
    const STATUS_TRANSFERRED = 40;

    private $apibridge;

    public function __construct() {
        $this->apibridge = apibridge::get_instance();
    }

    public static function get_status_string($statuscode) {

        switch ($statuscode) {
            case self::STATUS_READY_TO_UPLOAD :
                return get_string('mstatereadytoupload', 'block_opencast');
            case self::STATUS_CREATING_GROUP :
                return get_string('mstatecreatinggroup', 'block_opencast');
            case self::STATUS_CREATING_SERIES :
                return get_string('mstatecreatingseries', 'block_opencast');
            case self::STATUS_CHECK_DUPLICATE :
                return get_string('mstatecheckduplicate', 'block_opencast');
            case self::STATUS_CREATING_MEDIA_PACKAGE :
                return get_string('mstatecreatingmediapackage', 'block_opencast');
            case self::STATUS_UPLOADING_METADATA :
                return get_string('mstateuploadingmetadata', 'block_opencast');
            case self::STATUS_UPLOADING_ACL :
                return get_string('mstateuploadingacl', 'block_opencast');
            case self::STATUS_UPLOADING_PRESENTER :
                return get_string('mstateuploadingpresenter', 'block_opencast');
            case self::STATUS_UPLOADING_PRESENTATION :
                return get_string('mstateuploadingpresentation', 'block_opencast');
            case self::STATUS_UPLOADING_ATTACHMENTS :
                return get_string('mstateuploadingattachments', 'block_opencast');
            case self::STATUS_INGESTING :
                return get_string('mstateingesting', 'block_opencast');
            case self::STATUS_UPLOADED :
                return get_string('mstateuploaded', 'block_opencast');
            case self::STATUS_TRANSFERRED :
                return get_string('mstatetransferred', 'block_opencast');
            default :
                return get_string('mstateunknown', 'block_opencast');
        }
    }

    /**
     * Get all upload jobs, for which a file is present in the filearea of plugin.
     *
     * @param int $courseid
     *
     * @return array
     */
    public static function get_upload_jobs($courseid) {
        global $DB;

        $allnamefields = get_all_user_name_fields(true, 'u');

        $sql = "SELECT uj.*, $allnamefields, md.metadata,
                f1.filename as presenter_filename, f1.filesize as presenter_filesize,
                f2.filename as presentation_filename, f2.filesize as presentation_filesize
                FROM {block_opencast_uploadjob} uj
                JOIN {user} u ON uj.userid = u.id
                JOIN {block_opencast_metadata} md ON uj.id = md.uploadjobid
                LEFT JOIN {files} f1 ON f1.id = uj.presenter_fileid
                LEFT JOIN {files} f2 ON f2.id = uj.presentation_fileid
                WHERE uj.status < :status AND uj.courseid = :courseid";

        $params = [];
        $params['status'] = self::STATUS_TRANSFERRED;
        $params['courseid'] = $courseid;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Check uploadjobs, which means:
     *
     * 1. Add a new job for an completely uploaded to moodlle-video, when no entry in block_opencast_uploadjob exists
     * 2. Remove upload job, when status is readytoupload.
     *
     */
    public static function save_upload_jobs($courseid, $coursecontext , $options) {
        global $DB, $USER;

        // Find the current video files for the jobs.
        $params = [];
        $params['component'] = 'block_opencast';
        $params['filearea'] = self::OC_FILEAREA_VIDEO;
        $items = [];

        if (isset($options->presentation) && !empty($options->presentation)) {
            $items[] = $options->presentation;
        } else {
            $items[] = 0;
        }

        if (isset($options->presenter) && !empty($options->presenter)) {
            $items[] = $options->presenter;
        } else {
            $items[] = 0;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($items, SQL_PARAMS_NAMED);
        $params+=$inparams;
        $sql = "SELECT f.id, f.contenthash, f.itemid, f.filename, f.filesize FROM {files} f 
                    WHERE f.component = :component 
                        AND f.filearea = :filearea 
                        AND f.filename <> '.'
                        AND f.itemid {$insql}";
        
        $currentjobfiles = $DB->get_records_sql($sql, $params);
        $job = new \stdClass();
        foreach ($currentjobfiles as $file) {
            if (isset($options->presenter) && $options->presenter == $file->itemid) {
                $job->presenter_fileid = $file->id;
                $job->contenthash_presenter = $file->contenthash;
            }
            if (isset($options->presentation) && $options->presentation == $file->itemid) {
                $job->presentation_fileid = $file->id;
                $job->contenthash_presentation = $file->contenthash;
            }
        }
        $job->opencasteventid = '';
        $job->countfailed = 0;
        $job->timestarted = 0;
        $job->timesucceeded = 0;
        $job->status = self::STATUS_READY_TO_UPLOAD;
        $job->courseid = $courseid;
        $job->userid = $USER->id;
        $job->timecreated = time();
        $job->timemodified = $job->timecreated;
        $uploadjobid = $DB->insert_record('block_opencast_uploadjob', $job);

        $options->uploadjobid = $uploadjobid;
        $DB->insert_record('block_opencast_metadata', $options);

        // Find the current attachment files for the jobs.
        foreach ($options->attachments as $attachment) {
            // Translate file itemid into file id.
            $params = [];
            $params['component'] = 'block_opencast';
            $params['filearea'] = self::OC_FILEAREA_ATTACHMENT;
            $params['itemid'] = $attachment->fileid;
            $sql = "SELECT id FROM {files}
                    WHERE component = :component
                        AND filearea = :filearea
                        AND filename <> '.'
                        AND itemid = :itemid";
            $attachment->fileid = $DB->get_field_sql($sql, $params);

            $attachment->uploadjobid = $uploadjobid;
            $DB->insert_record('block_opencast_attachment', $attachment);
        }

        // Delete all jobs with status ready to transfer, where video file is missing.
        $sql = "SELECT uj.id
                FROM {block_opencast_uploadjob} uj
                LEFT JOIN {files} f
                ON (
                    uj.presentation_fileid = f.id OR
                    uj.presenter_fileid = f.id
                ) AND
                  f.component = :component AND
                  f.filearea = :filearea AND
                  f.filename <> '.'
                WHERE f.id IS NULL AND uj.status = :status";

        $params = [];
        $params['component'] = 'block_opencast';
        $params['filearea'] = self::OC_FILEAREA_VIDEO;
        $params['status'] = self::STATUS_READY_TO_UPLOAD;

        $jobidstodelete = $DB->get_records_sql($sql, $params);

        if (!empty($jobidstodelete)) {
            $DB->delete_records_list('block_opencast_uploadjob', 'id', array_keys($jobidstodelete));
        }

        // Delete all jobs with status < uploaded, where attachment file is missing.
        $sql = "SELECT a.id AS id,
                    uj.id AS uploadjobid,
                    a.id AS attachmentid
                FROM {block_opencast_uploadjob} uj
                JOIN {block_opencast_attachment} a
                ON uj.id = a.uploadjobid
                LEFT JOIN {files} f
                ON (a.fileid = f.id) AND
                    f.component = :component AND
                    f.filearea = :filearea AND
                    f.filename <> '.'
                WHERE f.id IS NULL AND uj.status < :status";

        $params = [];
        $params['component'] = 'block_opencast';
        $params['filearea'] = self::OC_FILEAREA_ATTACHMENT;
        $params['status'] = self::STATUS_UPLOADED;

        $jobsandattachmentstodelete = $DB->get_records_sql($sql, $params);

        $attachmentidstodelete = array_column($jobsandattachmentstodelete, 'attachmentid');
        $jobidstodelete = array_column($jobsandattachmentstodelete, 'uploadjobid');

        $attachmentidstodelete = array_unique($attachmentidstodelete);
        $jobidstodelete = array_unique($jobidstodelete);

        if (!empty($attachmentidstodelete)) {
            $DB->delete_records_list('block_opencast_attachment', 'id', $attachmentidstodelete);
        }
        if (!empty($jobidstodelete)) {
            $DB->delete_records_list('block_opencast_uploadjob', 'id', $jobidstodelete);
        }
    }

    /**
     * If upload was successful, set:
     * - timesucceeded
     * - status to transferred
     * - timemodified
     *
     * and create event, delete file from files table.
     *
     * @param object $job
     * @param string $eventidentifier
     */
    protected function upload_succeeded($job, $eventidentifier) {
        global $DB;

        $job->opencasteventid = $eventidentifier;
        $job->timesucceeded = time();
        $job->timemodified = $job->timesucceeded;
        $job->status = self::STATUS_TRANSFERRED;

        $DB->update_record('block_opencast_uploadjob', $job);

        // Delete from files table.
        $fs = get_file_storage();

        $files = array();
        $job->presenter_fileid ? $files[] = $fs->get_file_by_id($job->presenter_fileid) : NULL;
        $job->presentation_fileid ? $files[] = $fs->get_file_by_id($job->presentation_fileid) : NULL;
        $filenames = array();
        foreach ($files as $file) {
            $filenames[] = (!$file) ? 'unknown' : $file->get_filename();
        }

        // Trigger event.
        $context = \context_course::instance($job->courseid);
        $event = \block_opencast\event\upload_succeeded::create(
            array(
                'context'  => $context,
                'objectid' => $job->id,
                'courseid' => $job->courseid,
                'userid'   => $job->userid,
                'other'    => array('filename' => implode(' & ', $filenames))
            )
        );

        $event->trigger();

        // Delete file from files table.
        $config = get_config('block_opencast');

        foreach ($files as $file) {
            $file->delete();
            if (!empty($config->adhocfiledeletion)) {
                file_deletionmanager::fulldelete_file($file);
            }
        }

        
    }

    protected function upload_failed($job, $errormessage) {
        global $DB;

        // Update the job to enqueue again.
        $job->countfailed++;
        $job->timemodified = time();
        $job->status = self::STATUS_READY_TO_UPLOAD;

        $DB->update_record('block_opencast_uploadjob', $job);

        // Get file information.
        $fs = get_file_storage();
        $files = array();
        $job->presenter_fileid ? $files[] = $fs->get_file_by_id($job->presenter_fileid) : NULL;
        $job->presentation_fileid ? $files[] = $fs->get_file_by_id($job->presentation_fileid) : NULL;

        $filenames = array();
        foreach ($files as $file) {
            $filenames[] = (!$file) ? 'unknown' : $file->get_filename();
        }

        // Trigger event.
        $context = \context_course::instance($job->courseid);
        $event = \block_opencast\event\upload_failed::create(
            array(
                'context'  => $context,
                'objectid' => $job->id,
                'courseid' => $job->courseid,
                'userid'   => $job->userid,
                'other'    => array(
                    'filename'     => /* $filename */implode(' & ', $filenames),
                    'errormessage' => $errormessage,
                    'countfailed'  => $job->countfailed
                )
            )
        );

        $event->trigger();
    }

    /**
     * Updates the status of a job and sets the time values accordingly.
     *
     * @param object $job          job to be updated.
     * @param int    $status       the new status of the job. See the predefined constants of the class for available choices.
     * @param bool   $setmodified  if true, the value timemodified of the job is set to the current time.
     * @param bool   $setstarted   if true, the value timestarted of the job is set to the current time.
     * @param bool   $setsucceeded if true, the value timesucceeded of the job is set to the current time.
     */
    protected function update_status(&$job, $status, $setmodified = true, $setstarted = false, $setsucceeded = false) {
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
        $job->status = $status;

        $DB->update_record('block_opencast_uploadjob', $job);
    }

    /**
     * Processes the different work packages of the upload job. Since there are some tasks, which are processed by
     * opencast in an asynchronous fashion, this method can either return the created opencast event or false. In case
     * of false, the cronjob has to rerun this task later.
     *
     * @param object $job represents the upload job.
     *
     * @return false | object either false -> rerun later or object -> upload successful.
     * @throws \moodle_exception
     */
    protected function process_upload_job($job) {
        global $DB;
        $stepsuccessful = false;

        switch ($job->status) {
            case self::STATUS_READY_TO_UPLOAD:
                $this->update_status($job, self::STATUS_CREATING_GROUP, true, true);
            case self::STATUS_CREATING_GROUP:
                if (get_config('block_opencast', 'group_creation')) {
                    try {
                        // Check if group exists.
                        $group = $this->apibridge->ensure_acl_group_exists($job->courseid);
                        if ($group) {
                            $stepsuccessful = true;
                            mtrace('... group exists');
                            // Move on to next status.
                            $this->update_status($job, self::STATUS_CREATING_SERIES);
                        }
                    } catch (opencast_state_exception $e) {
                        mtrace('... group creation still in progress');
                    }
                    break;
                } else {
                    // Move on to next status.
                    $this->update_status($job, self::STATUS_CREATING_SERIES);
                }

            case self::STATUS_CREATING_SERIES:
                try {
                    // Check if series exists.
                    $series = $this->apibridge->ensure_course_series_exists($job->courseid);
                    if ($series) {
                        $stepsuccessful = true;
                        mtrace('... series exists');
                        // Move on to next status.
                        $this->update_status($job, self::STATUS_CREATING_MEDIA_PACKAGE);
                    }
                } catch (opencast_state_exception $e) {
                    mtrace('... series creation still in progress');
                }
                break;

            case self::STATUS_CHECK_DUPLICATE:
                if (!get_config('block_opencast', 'reuseexistingupload')) {
                    // We don't reuse existing uploads, so continue as usual.
                    $stepsuccessful = true;
                    $this->update_status($job, self::STATUS_CREATING_MEDIA_PACKAGE);
                    break;
                }

                // Check, whether any of the video files were uploaded any time before.
                $params = array(
                    'contenthash_presenter' => $job->contenthash_presenter,
                    'contenthash_presentation' => $job->contenthash_presentation
                );

                // Search for files already uploaded to opencast.
                $sql = "SELECT opencasteventid
                    FROM {block_opencast_uploadjob}
                    WHERE contenthash_presenter = :contenthash_presenter
                        OR contenthash_presentation = :contenthash_presentation
                    GROUP BY opencasteventid ";

                $eventids = $DB->get_records_sql($sql, $params);
                if ($eventids) {
                    $eventids = array_keys($eventids);
                    mtrace('... applying reuse of existing upload');
                }

                $event = $this->apibridge->get_already_existing_event($eventids);

                // Check result.
                if (!isset($event->identifier)) {
                    // None of the videos are uploaded yet, so continue.
                    $stepsuccessful = true;
                    $this->update_status($job, self::STATUS_CREATING_MEDIA_PACKAGE);
                } else {
                    $stepsuccessful = true;
                    mtrace('... video uploaded');
                    $this->update_status($job, self::STATUS_UPLOADED);

                    // Update eventid.
                    $job->opencasteventid = $event->identifier;
                    $DB->update_record('block_opencast_uploadjob', $job);
                }
                break;

            case self::STATUS_CREATING_MEDIA_PACKAGE:
                // Create empty media package.
                $mediapackagexml = $this->apibridge->create_media_package();
                $stepsuccessful = true;

                // Update mediapackagexml and eventid.
                $job->mediapackagexml = $mediapackagexml;
                $mediapackage = simplexml_load_string($mediapackagexml);
                $job->opencasteventid = (string)$mediapackage->attributes()['id'];
                $DB->update_record('block_opencast_uploadjob', $job);

                $this->update_status($job, self::STATUS_UPLOADING_METADATA);
                mtrace('... media package created');
                break;

            case self::STATUS_UPLOADING_METADATA:
                $mediapackagexml = $this->apibridge->media_package_upload_metadata($job);
                $stepsuccessful = true;

                // Update mediapackagexml.
                $job->mediapackagexml = $mediapackagexml;
                $DB->update_record('block_opencast_uploadjob', $job);

                $this->update_status($job, self::STATUS_UPLOADING_ACL);
                mtrace('... media package metadata uploaded');
                break;

            case self::STATUS_UPLOADING_ACL:
                $mediapackagexml = $this->apibridge->media_package_upload_acl($job);
                $stepsuccessful = true;

                // Update mediapackagexml.
                $job->mediapackagexml = $mediapackagexml;
                $DB->update_record('block_opencast_uploadjob', $job);

                $this->update_status($job, self::STATUS_UPLOADING_PRESENTER);
                mtrace('... media package ACL uploaded');
                break;

            case self::STATUS_UPLOADING_PRESENTER:
                if ($job->presenter_fileid) {
                    $mediapackagexml = $this->apibridge->media_package_upload_presenter($job);
                    $stepsuccessful = true;

                    // Update mediapackagexml.
                    $job->mediapackagexml = $mediapackagexml;
                    $DB->update_record('block_opencast_uploadjob', $job);

                    mtrace('... presenter video uploaded');
                } else {
                    mtrace('... no presenter video provided');
                    $stepsuccessful = true;
                }
                $this->update_status($job, self::STATUS_UPLOADING_PRESENTATION);
                break;

            case self::STATUS_UPLOADING_PRESENTATION:
                if ($job->presentation_fileid) {
                    $mediapackagexml = $this->apibridge->media_package_upload_presentation($job);
                    $stepsuccessful = true;

                    // Update mediapackagexml.
                    $job->mediapackagexml = $mediapackagexml;
                    $DB->update_record('block_opencast_uploadjob', $job);

                    mtrace('... presentation video uploaded');
                } else {
                    mtrace('... no presentation video provided');
                    $stepsuccessful = true;
                }
                $this->update_status($job, self::STATUS_UPLOADING_ATTACHMENTS);
                break;

            case self::STATUS_UPLOADING_ATTACHMENTS:
                if (!empty($job->attachments)) {
                    $mediapackagexml = $this->apibridge->media_package_upload_attachments($job);
                    $stepsuccessful = true;

                    // Update mediapackagexml.
                    $job->mediapackagexml = $mediapackagexml;
                    $DB->update_record('block_opencast_uploadjob', $job);

                    mtrace('... attachments uploaded');
                } else {
                    mtrace('... no attachments provided');
                    $stepsuccessful = true;
                }
                $this->update_status($job, self::STATUS_INGESTING);
                break;

            case self::STATUS_INGESTING:
                $mediapackagexml = $this->apibridge->media_package_ingest($job);
                $stepsuccessful = true;

                // Update mediapackagexml.
                $job->mediapackagexml = $mediapackagexml;
                $DB->update_record('block_opencast_uploadjob', $job);

                $this->update_status($job, self::STATUS_UPLOADED);
                mtrace('... workflow started uploaded');
                break;

            case self::STATUS_UPLOADED:

                // Continue with post-upload tasks.

                // Verify the upload.
                if (!$job->opencasteventid) {
                    throw new \moodle_exception('missingeventidentifier', 'block_opencast');
                }

                if (!($event = $this->apibridge->get_already_existing_event(array($job->opencasteventid)))) {
                    mtrace('... event does not exist');
                    break;
                }

                // Mark the job as uploaded.
                $job->opencasteventid = $event->identifier;

                // For a new event do acl and series are already set, event will be processed
                // in opencast, so it is not possible to modify event at this state.
                if (isset($event->newlycreated) && $event->newlycreated) {
                    return $event;
                }

                if (get_config('block_opencast', 'group_creation')) {
                    // Ensure the assignment of a suitable role.
                    if (!$this->apibridge->ensure_acl_group_assigned($event->identifier, $job->courseid)) {
                        mtrace('... group not yet assigned.');
                        break;
                    }
                    mtrace('... group assigned');
                }

                // If a event was created, the upload is finished and the event can be returned.
                return $event;
        }

        // If the current step (creation of group or series) was successful, the function process_upload_job can be
        // called recursively to continue with the next bit of work.
        if ($stepsuccessful) {
            return $this->process_upload_job($job);
        } else {
            // Otherwise, false is returned, which causes to cronjob to try again later.
            return false;
        }
    }

    /**
     * Process all transfers to opencast server.
     */
    public function cron() {
        global $DB;

        // Get all waiting jobs.
        $sql = "SELECT * FROM {block_opencast_uploadjob}
                WHERE status < ? ORDER BY timemodified ASC ";

        $limituploadjobs = get_config('block_opencast', 'limituploadjobs');

        $jobs = $DB->get_records_sql($sql, array(self::STATUS_TRANSFERRED), 0, $limituploadjobs);

        if (!$jobs) {
            mtrace('...no jobs to proceed');
        }

        foreach ($jobs as $job) {
            mtrace('proceed: ' . $job->id);
            try {
                $jobmetadata = $DB->get_record('block_opencast_metadata', array('uploadjobid' => $job->id), $fields='metadata', $strictness=IGNORE_MISSING);
                if ($jobmetadata) {
                    $job = (object) array_merge((array)$job, (array)$jobmetadata );
                }
                $job->attachments = $this->get_opencast_attachments($job->id);
                $event = $this->process_upload_job($job);
                if ($event) {
                    $this->upload_succeeded($job, $event->identifier);
                } else {
                    mtrace('job ' . $job->id . ' is postponed');
                }
            } catch (\moodle_exception $e) {
                mtrace('Job failed due to: ' . $e);
                $this->upload_failed($job, $e->getMessage());
            }
        }
    }

    /**
     * The upload form should be called in block context if possible to allow
     * unlimited upload size for special users.
     *
     * Try to find the block context here.
     *
     * @param int $courseid
     * @return object
     */
    public static function get_opencast_upload_context($courseid) {
        global $DB;

        $sql = "SELECT bi.* FROM {block_instances} bi
                JOIN {context} ctx on ctx.id = bi.parentcontextid AND ctx.contextlevel = :contextlevel
                WHERE bi.blockname = :blockname AND ctx.instanceid = :instanceid";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'blockname' => 'opencast',
            'instanceid' => $courseid
        ];

        if (!$blockinstance = $DB->get_record_sql($sql, $params)) {
            return \context_course::instance($courseid);
        }

        return \context_block::instance($blockinstance->id);
    }

    //metadata

    /**
     * Gets the catalog of metadata fields from database
     *
     * @return mixed $metadata the metadata fields
     */
    public static function get_opencast_metadata_catalog($condition = array(), $fields = '*') {
        global $DB;

        if ($condition) {
            $metadata_catalog = $DB->get_record('block_opencast_catalog', $condition, $fields );
        } else {
            $metadata_catalog = $DB->get_records('block_opencast_catalog', null, 'id');
        }

        if (!$metadata_catalog) {
            return false;
        }

        return $metadata_catalog;
    }

    // Attachments.

    public static function get_opencast_attachment_fields($condition = [], $fields = '*') {
        global $DB;

        if ($condition) {
            $attachfields = $DB->get_record('block_opencast_attach_field', $condition, $fields );
        } else {
            $attachfields = $DB->get_records('block_opencast_attach_field', null, 'id');
        }

        if (!$attachfields) {
            return false;
        }

        return $attachfields;
    }

    public static function get_opencast_attachments($uploadjobid) {
        global $DB;

        $sql = "SELECT a.id, a.fileid, af.name, af.asset_id, af.type, af.flavor_type, af.flavor_subtype
                FROM {block_opencast_attachment} a
                JOIN {block_opencast_attach_field} af
                    ON a.attachfieldid = af.id
                WHERE a.uploadjobid = :uploadjobid";
        $params = [];
        $params['uploadjobid'] = $uploadjobid;

        return $DB->get_records_sql($sql, $params);
    }

}
