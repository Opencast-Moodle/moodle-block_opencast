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

require_once($CFG->dirroot . '/lib/filelib.php');

class upload_helper {

    const OC_FILEAREA = 'videotoupload';
    const STATUS_READY_TO_UPLOAD = 10;
    const STATUS_UPLOADING = 20;
    const STATUS_TRANSFERRED = 40;

    private $apibridge;

    public function __construct() {
        $this->apibridge = \block_opencast\local\apibridge::get_instance();
    }

    public static function get_status_string($statuscode) {

        switch ($statuscode) {
            case self::STATUS_READY_TO_UPLOAD :
                return get_string('mstatereadytoupload', 'block_opencast');
            case self::STATUS_UPLOADING :
                return get_string('mstatesuploading', 'block_opencast');
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
     * @return array
     */
    public static function get_upload_jobs($courseid) {
        global $DB;

        $allnamefields = get_all_user_name_fields(true, 'u');

        $sql = "SELECT uj.*, f.filename, f.filesize, $allnamefields
                FROM {block_opencast_uploadjob} uj
                JOIN {files} f ON uj.fileid = f.id AND f.component = :component
                JOIN {user} u ON f.userid = u.id
                AND f.filearea = :filearea AND f.filename <> '.'
                WHERE uj.status < :status AND uj.courseid = :courseid";

        $params = [];
        $params['component'] = 'block_opencast';
        $params['filearea'] = self::OC_FILEAREA;
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
    public static function save_upload_jobs($courseid, $coursecontext) {
        global $DB, $USER;

        // Get all area files where the upload job is missing and add a job.
        $sql = "SELECT f.id, f.contenthash, uj.fileid
                FROM {files} f
                LEFT JOIN {block_opencast_uploadjob} uj ON f.id = uj.fileid
                WHERE f.component = :component AND f.filearea = :filearea AND f.filename <> '.'
                AND uj.fileid IS NULL ";

        $params = [];
        $params['component'] = 'block_opencast';
        $params['filearea'] = self::OC_FILEAREA;

        $filesneedingjobs = $DB->get_records_sql($sql, $params);

        foreach ($filesneedingjobs as $file) {

            $job = new \stdClass();
            $job->fileid = $file->id;
            $job->contenthash = $file->contenthash;
            $job->opencasteventid = '';
            $job->countfailed = 0;
            $job->timestarted = 0;
            $job->timesucceeded = 0;
            $job->status = self::STATUS_READY_TO_UPLOAD;
            $job->courseid = $courseid;
            $job->userid = $USER->id;
            $job->timecreated = time();
            $job->timemodified = $job->timecreated;

            $DB->insert_record('block_opencast_uploadjob', $job);
        }

        // Delete all jobs with status ready to transfer, where file is missing.
        $sql = "SELECT uj.id
                FROM {block_opencast_uploadjob} uj
                LEFT JOIN {files} f ON uj.fileid = f.id AND f.component = :component AND f.filearea = :filearea AND f.filename <> '.'
                WHERE f.id IS NULL AND uj.status = :status";

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
        $file = $fs->get_file_by_id($job->fileid);

        $filename = (!$file) ? 'unknown' : $file->get_filename();

        // Trigger event.
        $context = \context_course::instance($job->courseid);
        $event = \block_opencast\event\upload_succeeded::create(
                array(
                    'context' => $context,
                    'objectid' => $job->id,
                    'courseid' => $job->courseid,
                    'userid' => $job->userid,
                    'other' => array('filename' => $filename)
                )
        );

        $event->trigger();

        // Delete file from files table.
        $file->delete();
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
        $file = $fs->get_file_by_id($job->fileid);

        $filename = (!$file) ? 'unknown' : $file->get_filename();

        // Trigger event.
        $context = \context_course::instance($job->courseid);
        $event = \block_opencast\event\upload_failed::create(
                array(
                    'context' => $context,
                    'objectid' => $job->id,
                    'courseid' => $job->courseid,
                    'userid' => $job->userid,
                    'other' => array(
                        'filename' => $filename,
                        'errormessage' => $errormessage,
                        'countfailed' => $job->countfailed
                    )
                )
        );

        $event->trigger();
    }

    /**
     *
     * @param type $job
     * @return type
     * @throws \moodle_exception
     */
    protected function process_upload_job($job) {
        global $DB;

        $job->status = self::STATUS_UPLOADING;
        if (empty($job->timestarted)) {
            $job->timestarted = time();
        }
        $DB->update_record('block_opencast_uploadjob', $job);

        // If executing from unittest change courseid to avoid collision with real uploads.
        if (PHPUNIT_TEST) {
            $job = clone($job);
            $job->courseid = 'oc_utest';
        }

        // Check if role and series exists.
        $group = $this->apibridge->ensure_acl_group_exists($job->courseid);
        mtrace('... group exists');

        $series = $this->apibridge->ensure_course_series_exists($job->courseid);
        mtrace('... series exists');

        // Check, whether this file was uploaded any time before.
        $params = array(
            'contenthash' => $job->contenthash
        );

        // Search for files already uploaded to opencast.
        $sql = "SELECT opencasteventid
                FROM {block_opencast_uploadjob}
                WHERE contenthash = :contenthash
                GROUP BY opencasteventid ";

        $eventids = $DB->get_records_sql($sql, $params);
        if ($eventids) {
            $eventids = array_keys($eventids);
        }

        $event = $this->apibridge->ensure_event_exists($job, $eventids, $series->identifier);
        mtrace('... video uploaded');

        // Check result.
        if (!isset($event->identifier)) {
            throw new \moodle_exception('missingevent', 'block_opencast');
        }

        // Verify the upload.
        if (!$this->apibridge->get_already_existing_event(array($event->identifier))) {
            throw new \moodle_exception('missingeventidentifier', 'block_opencast');
        }

        // Mark the job as uploaded.
        $job->opencasteventid = $event->identifier;

        // For a new event do acl and series are already set, event will be processed
        // in opencast, so it is not possible to modify event at this state.
        if ($event->newlycreated) {
            return $event;
        }

        // Ensure the assignment of a suitable role.
        if (!$this->apibridge->ensure_acl_group_assigned($event->identifier, $job->courseid)) {
            throw new \moodle_exception('missingaclassignment', 'block_opencast');
        }
        mtrace('... group assigned');

        // Ensure the assignment of a series.
        if (!$this->apibridge->ensure_series_assigned($event->identifier, $series->identifier)) {
            throw new \moodle_exception('missingseriesassignment', 'block_opencast');
        }
        mtrace('... series assigned');

        return $event;
    }

    /**
     * Process all transfers to opencast server.
     */
    public function cron() {
        global $DB;

        // Get all waiting jobs.
        $sql = "SELECT * FROM {block_opencast_uploadjob}
                WHERE status = ? ORDER BY timemodified ASC ";

        $limituploadjobs = get_config('block_opencast', 'limituploadjobs');

        $jobs = $DB->get_records_sql($sql, array(self::STATUS_READY_TO_UPLOAD), 0, $limituploadjobs);

        if (!$jobs) {
            mtrace('...no jobs to proceed');
        }

        foreach ($jobs as $job) {
            mtrace('proceed: ' . $job->id);
            try {
                $event = $this->process_upload_job($job);
                $this->upload_succeeded($job, $event->identifier);
            } catch (\moodle_exception $e) {
                $this->upload_failed($job, $e->getMessage());
            }
        }
    }

}
