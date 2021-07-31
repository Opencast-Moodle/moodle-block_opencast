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
 * Upload helper.
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/filelib.php');

use block_opencast\opencast_state_exception;
use local_chunkupload\chunkupload_form_element;
use tool_opencast\seriesmapping;

/**
 * Upload helper.
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_helper
{

    /** @var string File area id where videos are uploaded */
    const OC_FILEAREA = 'videotoupload';

    /** @var int Video is ready to be uploaded */
    const STATUS_READY_TO_UPLOAD = 10;

    /** @var int Group is created */
    const STATUS_CREATING_GROUP = 21;

    /** @var int Series is created */
    const STATUS_CREATING_SERIES = 22;

    /** @var int Event is created */
    const STATUS_CREATING_EVENT = 25;

    /** @var int Video is successfully uploaded */
    const STATUS_UPLOADED = 27;

    /** @var int Video is successfully transferred to Opencast. */
    const STATUS_TRANSFERRED = 40;

    /**
     * Get explaination string for status code
     * @param int $statuscode Status code
     * @return \lang_string|string Name of status code
     */
    public static function get_status_string($statuscode) {

        switch ($statuscode) {
            case self::STATUS_READY_TO_UPLOAD :
                return get_string('mstatereadytoupload', 'block_opencast');
            case self::STATUS_CREATING_GROUP :
                return get_string('mstatecreatinggroup', 'block_opencast');
            case self::STATUS_CREATING_SERIES :
                return get_string('mstatecreatingseries', 'block_opencast');
            case self::STATUS_CREATING_EVENT :
                return get_string('mstatecreatingevent', 'block_opencast');
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
     * @param int $courseid Course id
     * @return array
     */
    public static function get_upload_jobs($ocinstanceid, $courseid) {
        global $DB, $CFG;

        if ($CFG->branch >= 311) {
            $allnamefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        } else {
            $allnamefields = get_all_user_name_fields(true, 'u');
        }
        $select = "SELECT uj.*, $allnamefields, md.metadata, " .
            "f1.filename as presenter_filename, f1.filesize as presenter_filesize, " .
            "f2.filename as presentation_filename, f2.filesize as presentation_filesize";
        $from = " FROM {block_opencast_uploadjob} uj " .
            "JOIN {user} u ON uj.userid = u.id " .
            "JOIN {block_opencast_metadata} md ON uj.id = md.uploadjobid " .
            "LEFT JOIN {files} f1 ON f1.id = uj.presenter_fileid " .
            "LEFT JOIN {files} f2 ON f2.id = uj.presentation_fileid";

        if (class_exists('\local_chunkupload\chunkupload_form_element')) {
            $select .= ", cu1.filename as presenter_chunkupload_filename, " .
                "cu2.filename as presentation_chunkupload_filename, " .
                "cu1.length as presenter_chunkupload_filesize, " .
                "cu2.length as presentation_chunkupload_filesize";
            $from .= " LEFT JOIN {local_chunkupload_files} cu1 ON uj.chunkupload_presenter = cu1.id " .
                "LEFT JOIN {local_chunkupload_files} cu2 ON uj.chunkupload_presentation = cu2.id";
        }

        $where = " WHERE uj.status < :status AND uj.courseid = :courseid AND uj.ocinstanceid = :ocinstanceid ORDER BY uj.timecreated DESC";

        $sql = $select . $from . $where;

        $params = [];
        $params['status'] = self::STATUS_TRANSFERRED;
        $params['courseid'] = $courseid;
        $params['ocinstanceid'] = $ocinstanceid;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Check uploadjobs, which means:
     *
     * 1. Add a new job for an completely uploaded to moodlle-video, when no entry in block_opencast_uploadjob exists
     * 2. Remove upload job, when status is readytoupload.
     *
     * @param int $courseid Course id
     * @param object $coursecontext Course context
     * @param object $options Options
     */
    public static function save_upload_jobs($ocinstanceid, $courseid, $coursecontext, $options) {
        global $DB, $USER;

        // Find the current files for the jobs.
        $params = [];
        $params['component'] = 'block_opencast';
        $params['filearea'] = self::OC_FILEAREA;
        $items = array();

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
        $params += $inparams;
        $sql = "SELECT f.id, f.contenthash, f.itemid, f.filename, f.filesize FROM {files} f " .
            "WHERE f.component = :component " .
            "AND f.filearea = :filearea " .
            "AND f.filename <> '.' " .
            "AND f.itemid {$insql}";

        $currentjobfiles = $DB->get_records_sql($sql, $params);
        $job = new \stdClass();
        foreach ($currentjobfiles as $file) {
            if (isset($options->presenter) && $options->presenter == $file->itemid) {
                $job->presenter_fileid = $file->id;
                $job->contenthash_presenter = $file->contenthash;
            }
            if (isset($options->presentation) && $options->presentation == $file->itemid) {
                $job->presentation_fileid = $file->id;
                $job->contenthash_presenter = $file->contenthash;
            }
        }

        // Add chunkupload references.
        if (class_exists('\local_chunkupload\chunkupload_form_element')) {
            if (isset($options->chunkupload_presenter) && $options->chunkupload_presenter) {
                $record = $DB->get_record('local_chunkupload_files', array('id' => $options->chunkupload_presenter),
                    '*', IGNORE_MISSING);
                if ($record && $record->state == 2) {
                    $job->chunkupload_presenter = $options->chunkupload_presenter;
                }
            }
            if (isset($options->chunkupload_presenter) && $options->chunkupload_presenter) {
                $record = $DB->get_record('local_chunkupload_files', array('id' => $options->chunkupload_presentation),
                    '*', IGNORE_MISSING);
                if ($record && $record->state == 2) {
                    $job->chunkupload_presentation = $options->chunkupload_presentation;
                }
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
        $job->ocinstanceid = $ocinstanceid;
        $uploadjobid = $DB->insert_record('block_opencast_uploadjob', $job);

        $options->uploadjobid = $uploadjobid;
        $DB->insert_record('block_opencast_metadata', $options);

        // Delete all jobs with status ready to transfer, where file is missing.
        $sql = "SELECT uj.id " .
            "FROM {block_opencast_uploadjob} uj " .
            "LEFT JOIN {files} f " .
            "ON (uj.presentation_fileid = f.id OR uj.presenter_fileid = f.id) AND " .
            "f.component = :component AND " .
            "f.filearea = :filearea AND " .
            "f.filename <> '.'";

        $where = " WHERE f.id IS NULL AND uj.status = :status";

        // If chunkupload exists add additional requirements to the statement.
        if (class_exists('\local_chunkupload\chunkupload_form_element')) {
            $sql .= " LEFT JOIN {local_chunkupload_files} cu " .
                "ON cu.id = uj.chunkupload_presenter OR cu.id = uj.chunkupload_presentation";
            $where .= " AND cu.id IS NULL";
        }

        // Append where to sql.
        $sql .= $where;

        $params = [];
        $params['component'] = 'block_opencast';
        $params['filearea'] = self::OC_FILEAREA;
        $params['status'] = self::STATUS_READY_TO_UPLOAD;

        $jobidstodelete = $DB->get_records_sql($sql, $params);

        if (!empty($jobidstodelete)) {
            $DB->delete_records_list('block_opencast_uploadjob', 'id', array_keys($jobidstodelete));
        }
    }

    /**
     * deletes the video file from file system and removes the job from upload queue as if the video had never been uploaded
     * deletes only if the status is STATUS_READY_TO_UPLOAD
     *
     * @param \stdClass $jobtodelete
     * @return bool
     * @throws \dml_exception
     */
    public static function delete_video_draft($jobtodelete) {
        global $DB;
        // Check again shortly before deletion if the status is still STATUS_READY_TO_UPLOAD.
        if ($DB->record_exists('block_opencast_uploadjob',
            ['id' => $jobtodelete->id, 'status' => self::STATUS_READY_TO_UPLOAD])) {

            $DB->delete_records('block_opencast_uploadjob', ['id' => $jobtodelete->id]);
            $DB->delete_records('block_opencast_metadata', ['uploadjobid' => $jobtodelete->id]);
            // Delete from files table.
            $fs = get_file_storage();
            $files = array();
            $jobtodelete->presenter_fileid ? $files[] = $fs->get_file_by_id($jobtodelete->presenter_fileid) : null;
            $jobtodelete->presentation_fileid ? $files[] = $fs->get_file_by_id($jobtodelete->presentation_fileid) : null;
            foreach ($files as $file) {
                $file->delete();
                file_deletionmanager::fulldelete_file($file);
            }
            return true;
        }
        return false;
    }

    /**
     * If upload was successful, set:
     * - timesucceeded
     * - status to transferred
     * - timemodified
     *
     * and create event, delete file from files table.
     * and add notification job for the uploaded video if the admin setting is enabled.
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

        // Delete from chunkupload.
        if (class_exists('\local_chunkupload\chunkupload_form_element')) {
            if ($job->chunkupload_presenter) {
                chunkupload_form_element::delete_file($job->chunkupload_presenter);
            }
            if ($job->chunkupload_presentation) {
                chunkupload_form_element::delete_file($job->chunkupload_presentation);
            }
        }

        // Delete from files table.
        $fs = get_file_storage();

        $files = array();
        $job->presenter_fileid ? $files[] = $fs->get_file_by_id($job->presenter_fileid) : null;
        $job->presentation_fileid ? $files[] = $fs->get_file_by_id($job->presentation_fileid) : null;
        $filenames = array();
        foreach ($files as $file) {
            $filenames[] = (!$file) ? 'unknown' : $file->get_filename();
        }

        // Trigger event.
        $context = \context_course::instance($job->courseid);
        $event = \block_opencast\event\upload_succeeded::create(
            array(
                'context' => $context,
                'objectid' => $job->id,
                'courseid' => $job->courseid,
                'userid' => $job->userid,
                'other' => array('filename' => implode(' & ', $filenames), 'ocinstanceid' => $job->ocinstanceid)
            )
        );

        $event->trigger();

        // Delete file from files table.
        $adhocfiledeletion = get_config('block_opencast', 'adhocfiledeletion_' . $job->ocinstanceid);

        foreach ($files as $file) {
            $file->delete();
            if (!empty($adhocfiledeletion)) {
                file_deletionmanager::fulldelete_file($file);
            }
        }

        // Get admin config, whether to send notification or not.
        $notificationenabled = get_config('block_opencast', 'eventstatusnotificationenabled');
        if ($notificationenabled) {
            // Add the uploaded video for the event status notification job.
            eventstatus_notification_helper::save_notification_jobs($eventidentifier, $job->courseid, $job->userid);
        }
    }

    /**
     * Handle failed upload.
     * @param object $job Job that failed
     * @param string $errormessage Error message
     */
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
        $job->presenter_fileid ? $files[] = $fs->get_file_by_id($job->presenter_fileid) : null;
        $job->presentation_fileid ? $files[] = $fs->get_file_by_id($job->presentation_fileid) : null;

        $filenames = array();
        foreach ($files as $file) {
            $filenames[] = (!$file) ? 'unknown' : $file->get_filename();
        }

        // Trigger event.
        $context = \context_course::instance($job->courseid);
        $event = \block_opencast\event\upload_failed::create(
            array(
                'context' => $context,
                'objectid' => $job->id,
                'courseid' => $job->courseid,
                'ocinstanceid' => $job->ocinstanceid,
                'userid' => $job->userid,
                'other' => array(
                    'filename' => /* $filename */ implode(' & ', $filenames),
                    'errormessage' => $errormessage,
                    'countfailed' => $job->countfailed
                )
            )
        );

        $event->trigger();
    }

    /**
     * Updates the status of a job and sets the time values accordingly.
     *
     * @param object $job job to be updated.
     * @param int $status the new status of the job. See the predefined constants of the class for available choices.
     * @param bool $setmodified if true, the value timemodified of the job is set to the current time.
     * @param bool $setstarted if true, the value timestarted of the job is set to the current time.
     * @param bool $setsucceeded if true, the value timesucceeded of the job is set to the current time.
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
        $apibridge = apibridge::get_instance($job->ocinstanceid);

        switch ($job->status) {
            case self::STATUS_READY_TO_UPLOAD:
                $this->update_status($job, self::STATUS_CREATING_GROUP, true, true);
            case self::STATUS_CREATING_GROUP:
                if (boolval(get_config('block_opencast', 'group_creation_' . $job->ocinstanceid))) {
                    try {
                        // Check if group exists.
                        $group = $apibridge->ensure_acl_group_exists($job->courseid, $job->userid);
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
                    $metadata = json_decode($job->metadata);
                    $mtseries = array_search('isPartOf', array_column($metadata, 'id'));
                    $series = null;

                    if ($mtseries !== false) {
                        $series = $apibridge->get_series_by_identifier($metadata[$mtseries]->value);
                    }

                    if (!$series) {
                        $series = $apibridge->ensure_course_series_exists($job->courseid, $job->userid);
                    }

                    if ($series) {
                        $stepsuccessful = true;
                        mtrace('... series exists');
                        // Move on to next status.
                        $this->update_status($job, self::STATUS_CREATING_EVENT);
                    }
                } catch (opencast_state_exception $e) {
                    mtrace('... series creation still in progress');
                }
                break;

            case self::STATUS_CREATING_EVENT:

                $eventids = array();

                if ($job->opencasteventid) {
                    array_push($eventids, $job->opencasteventid);
                } else if (get_config('block_opencast', 'reuseexistingupload_' . $job->ocinstanceid)) {
                    // Check, whether this file was uploaded any time before.
                    $params = array(
                        'contenthash_presenter' => $job->contenthash_presenter,
                        'contenthash_presentation' => $job->contenthash_presentation
                    );

                    // Search for files already uploaded to opencast.
                    $sql = "SELECT opencasteventid " .
                        "FROM {block_opencast_uploadjob} " .
                        "WHERE contenthash_presenter = :contenthash_presenter " .
                        "OR contenthash_presentation = :contenthash_presentation " .
                        "GROUP BY opencasteventid ";

                    $eventids = $DB->get_records_sql($sql, $params);
                    if ($eventids) {
                        $eventids = array_keys($eventids);
                        mtrace('... applying reuse of existing upload');
                    }
                }

                $metadata = json_decode($job->metadata);
                $mtseries = array_search('isPartOf', array_column($metadata, 'id'));
                $series = null;
                if ($mtseries !== false) {
                    $series = $apibridge->get_series_by_identifier($metadata[$mtseries]->value);
                }

                if (!$series) {
                    $series = $apibridge->get_default_course_series($job->courseid);

                    // Set series metadata.
                    if ($mtseries !== false) {
                        $metadata[$mtseries]->value = $series->identifier;
                    } else {
                        $metadata[] = array('id' => 'isPartOf', 'value' => $series->identifier);
                        $job->metadata = json_encode($metadata);
                    }
                }
                $event = $apibridge->ensure_event_exists($job, $eventids);

                // Check result.
                if (!isset($event->identifier)) {
                    throw new \moodle_exception('missingevent', 'block_opencast');
                } else {
                    mtrace('... video uploaded');
                    $this->update_status($job, self::STATUS_UPLOADED);
                    $stepsuccessful = true;

                    // Update eventid.
                    $job->opencasteventid = $event->identifier;
                    $DB->update_record('block_opencast_uploadjob', $job);
                }
                break;

            case self::STATUS_UPLOADED:

                // Continue with post-upload tasks.

                // Verify the upload.
                if (!$job->opencasteventid) {
                    throw new \moodle_exception('missingeventidentifier', 'block_opencast');
                }

                if (!($event = $apibridge->get_already_existing_event(array($job->opencasteventid)))) {
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

                if (boolval(get_config('block_opencast', 'group_creation_' . $job->ocinstanceid))) {
                    // Ensure the assignment of a suitable role.
                    if (!$apibridge->ensure_acl_group_assigned($event->identifier, $job->courseid, $job->userid)) {
                        mtrace('... group not yet assigned.');
                        break;
                    }
                    mtrace('... group assigned');
                }

                // Ensure the assignment of a course series.
                $assignedseries = $event->is_part_of;
                $courseseries = $DB->get_records('tool_opencast_series', array('courseid' => $job->courseid, 'ocinstanceid' => $job->ocinstanceid));

                if(!array_search($assignedseries, array_column($courseseries, 'series'))) {
                    // Try to assign series again.
                    $mtseries = array_search('isPartOf', array_column(json_decode($job->metadata), 'id'));

                    if(!array_search($mtseries, array_column($courseseries, 'series'))) {
                        $mtseries =  $apibridge->get_default_course_series($job->courseid)->identifier;
                    }

                    if (!$apibridge->assign_series($event->identifier, $mtseries)) {
                        mtrace('... series not yet assigned.');
                        break;
                    }
                }

                mtrace('... series assigned');

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

        $ocinstances = json_decode(get_config('tool_opencast', 'ocinstances'));
        foreach ($ocinstances as $ocinstance) {
            // Get all waiting jobs.
            $sql = "SELECT * FROM {block_opencast_uploadjob} WHERE status < ? AND ocinstanceid = ? ORDER BY timemodified ASC ";

            $limituploadjobs = get_config('block_opencast', 'limituploadjobs_' . $ocinstance->id);

            if (!$limituploadjobs) {
                $limituploadjobs = 0;
            }

            $jobs = $DB->get_records_sql($sql, array(self::STATUS_TRANSFERRED, $ocinstance->id), 0, $limituploadjobs);

            if (!$jobs) {
                mtrace('...no jobs to proceed for instance "' . $ocinstance->name . '"');
            }

            foreach ($jobs as $job) {
                mtrace('proceed: ' . $job->id);
                try {
                    $joboptions = $DB->get_record('block_opencast_metadata', array('uploadjobid' => $job->id),
                        $fields = 'metadata', $strictness = IGNORE_MISSING);
                    if ($joboptions) {
                        $job = (object)array_merge((array)$job, (array)$joboptions);
                    }
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

        $sql = "SELECT bi.* FROM {block_instances} bi " .
            "JOIN {context} ctx on ctx.id = bi.parentcontextid AND ctx.contextlevel = :contextlevel " .
            "WHERE bi.blockname = :blockname AND ctx.instanceid = :instanceid";

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

    // Metadata.

    /**
     * Gets the catalog of metadata fields from database
     *
     * @return stdClass $metadata the metadata object
     */
    public static function get_opencast_metadata_catalog($ocinstanceid) {
        $metadatacatalog = json_decode(get_config('block_opencast', 'metadata_' . $ocinstanceid));
        return $metadatacatalog;
    }
}
