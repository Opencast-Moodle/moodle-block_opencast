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
 * Notifications for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use core\message\message;
use core_user;
use Exception;

/**
 * Notifications for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications {


    /**
     * Helperfunction to send all following messages .
     *
     * @param string $messagetype Message type
     * @param object $touser User to which notification is sent
     * @param string $subject Subject
     * @param string $body Body
     * @param string $format Format
     */
    private static function send_message($messagetype, $touser, $subject, $body, $format = FORMAT_PLAIN) {

        $message = new message();
        $message->courseid = SITEID;
        $message->component = 'block_opencast';
        $message->name = $messagetype;
        $message->userfrom = core_user::get_user(core_user::NOREPLY_USER);
        $message->userto = $touser;
        $message->subject = $subject;
        $message->fullmessage = html_to_text($body);
        $message->fullmessageformat = $format;
        $message->fullmessagehtml = $body;
        $message->smallmessage = '';
        $message->notification = 1;

        message_send($message);
    }

    /**
     * Send notifications to admins, when a creating a course series has failed.
     *
     * @param int $courseid
     * @param array $backupeventids
     */
    public static function notify_failed_course_series($courseid, $backupeventids) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('errorrestoremissingseries_subj', 'block_opencast');
        $body = get_string('errorrestoremissingseries_body', 'block_opencast', $a);

        // Add all backup eventids.
        $renderer = $PAGE->get_renderer('block_opencast');
        $body .= $renderer->render_list($backupeventids);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, at least one event was not found on opencast
     * system during restore.
     *
     * @param int $courseid Course id
     * @param array $missingevents Missing events
     */
    public static function notify_missing_events($courseid, $missingevents) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('errorrestoremissingevents_subj', 'block_opencast');
        $body = get_string('errorrestoremissingevents_body', 'block_opencast', $a);

        // Add all backup eventids.
        $renderer = $PAGE->get_renderer('block_opencast');
        $body .= $renderer->render_list($missingevents);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Notify administrator upon an exception error.
     *
     * @param string $identifier
     * @param Exception $e
     */
    public static function notify_error($identifier, Exception $e = null) {

        $subject = get_string('erroremailsubj', 'block_opencast');

        $message = empty($e) ? '' : $e->getMessage();
        $errorstr = get_string($identifier, 'block_opencast', $identifier);
        $a = (object)[
            'message' => $message,
            'errorstr' => $errorstr,
        ];

        $body = get_string('erroremailbody', 'block_opencast', $a);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }


    /**
     * Notify user that email to support was successfully sent.
     * @param string $message Message that was sent to the support
     */
    public static function notify_problem_reported($message) {
        global $USER;

        self::send_message('reportproblem_confirmation', $USER,
            get_string('reportproblem_subject', 'block_opencast'), nl2br($message), FORMAT_MOODLE);
    }


    /**
     * Send notifications to admins, when the import mode could not be identified.
     *
     * @param int $courseid
     */
    public static function notify_failed_importmode($courseid) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('errorrestoremissingimportmode_subj', 'block_opencast');
        $body = get_string('errorrestoremissingimportmode_body', 'block_opencast', $a);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, when the conditions or required data to perform ACL Change were missing (sourcecourseid).
     *
     * @param int $courseid
     */
    public static function notify_missing_sourcecourseid($courseid) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('errorrestoremissingsourcecourseid_subj', 'block_opencast');
        $body = get_string('errorrestoremissingsourcecourseid_body', 'block_opencast', $a);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, when the conditions or required data to perform ACL Change were missing (seriesid).
     *
     * @param int $courseid
     */
    public static function notify_missing_seriesid($courseid) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('errorrestoremissingseriesid_subj', 'block_opencast');
        $body = get_string('errorrestoremissingseriesid_body', 'block_opencast', $a);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, when series ACL change was not successful.
     *
     * @param int $courseid
     * @param int $sourcecourseid
     * @param string $seriesid
     */
    public static function notify_failed_series_acl_change($courseid, $sourcecourseid, $seriesid) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'sourcecourseid' => $sourcecourseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'sourcecoursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'seriesid' => $seriesid,
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        if ($sourcecourse = $DB->get_record('course', ['id' => $sourcecourseid])) {
            $a->sourcecoursefullname = $sourcecourse->fullname;
        }

        $subject = get_string('errorrestorefailedseriesaclchange_subj', 'block_opencast');
        $body = get_string('errorrestorefailedseriesaclchange_body', 'block_opencast', $a);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, when series ACL change was not successful.
     *
     * @param int $courseid
     * @param int $sourcecourseid
     * @param array $failed falied events.
     */
    public static function notify_failed_events_acl_change($courseid, $sourcecourseid, $failed) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'sourcecourseid' => $sourcecourseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'sourcecoursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        if ($sourcecourse = $DB->get_record('course', ['id' => $sourcecourseid])) {
            $a->sourcecoursefullname = $sourcecourse->fullname;
        }

        $subject = get_string('errorrestorefailedeventsaclchange_subj', 'block_opencast');
        $body = get_string('errorrestorefailedeventsaclchange_body', 'block_opencast', $a);

        // Add all backup eventids.
        $renderer = $PAGE->get_renderer('block_opencast');
        $body .= $renderer->render_list($failed);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, when series mapping was not successful.
     *
     * @param int $courseid
     * @param int $sourcecourseid
     * @param string $seriesid
     */
    public static function notify_failed_series_mapping($courseid, $sourcecourseid, $seriesid) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'sourcecourseid' => $sourcecourseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'sourcecoursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'seriesid' => $seriesid,
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        if ($sourcecourse = $DB->get_record('course', ['id' => $sourcecourseid])) {
            $a->sourcecoursefullname = $sourcecourse->fullname;
        }

        $subject = get_string('errorrestorefailedseriesmapping_subj', 'block_opencast');
        $body = get_string('errorrestorefailedseriesmapping_body', 'block_opencast', $a);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Notify user about opencast event status after upload.
     * @param int $courseid Course id
     * @param object $touser User to which notification is sent
     * @param string $message the message containing the status of the event.
     * @param object $video the video object to get title and identifier.
     */
    public static function notify_event_status($courseid, $touser, $message, $video) {
        global $DB;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'videotitle' => $video->title,
            'videoidentifier' => $video->identifier,
            'statusmessage' => $message,
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('notificationeventstatus_subj', 'block_opencast');
        $body = get_string('notificationeventstatus_body', 'block_opencast', $a);

        self::send_message('opencasteventstatus_notification', $touser, $subject, $body);
    }

    /**
     * Notify user about upload queue.
     * @param int $courseid Course id
     * @param object $touser User to which notification is sent
     * @param int $waitingnum the number of jobs in the queue ahead
     * @param string $videotitle the title of the video
     */
    public static function notify_upload_queue_status($courseid, $touser, $waitingnum, $videotitle) {
        global $DB;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'videotitle' => $videotitle,
            'waitingnum' => $waitingnum,
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('notificationuploaduqeuestatus_subj', 'block_opencast');
        $body = get_string('notificationuploaduqeuestatus_body', 'block_opencast', $a);

        self::send_message('opencasteventstatus_notification', $touser, $subject, $body);
    }

    /**
     * Notify admin that imported modules cleanup task is removed, not in its normal way.
     * @param int $courseid Course id
     * @param int $workflowid The workflow id
     * @param string $notificationtype the type of the notification to determine the suitable message.
     * @param string $exceptionmessage extra exception message to pass along the normal message.
     */
    public static function notify_cleanup_imported_modules_force_deletion($courseid, $workflowid, $notificationtype,
                                                                          $exceptionmessage = '') {
        global $DB;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'workflowid' => $workflowid,
            'exceptionmessage' => $exceptionmessage,
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('notificationcleanupimportedmodules_subj', 'block_opencast');
        $body = get_string('notificationcleanupimportedmodulesgeneralexception_body', 'block_opencast', $a);

        switch ($notificationtype) {
            case 'expired':
                $body = get_string('notificationcleanupimportedmodulesexpired_body', 'block_opencast', $a);
                break;
            case 'cleanupfailed':
                $body = get_string('notificationcleanupimportedmodulescleanupfailed_body', 'block_opencast', $a);
                break;
            case 'noepisodeid':
                $body = get_string('notificationcleanupimportedmodulesnoeposideid_body', 'block_opencast', $a);
                break;
        }

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, when import mapping of series was not fully covered during restore.
     *
     * @param int $courseid Course id
     * @param array $missingimportmappingids Missing series/event ids
     * @param string $type either series or event
     */
    public static function notify_missing_import_mapping($courseid, $missingimportmappingids, $type = 'series') {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('errorrestoremissingimportmapping_subj', 'block_opencast');
        $body = get_string('errorrestoremissingimportmappingseries_body', 'block_opencast', $a);
        if ($type == 'events') {
            $body = get_string('errorrestoremissingimportmappingevents_body', 'block_opencast', $a);
        }

        // Add all backup eventids.
        $renderer = $PAGE->get_renderer('block_opencast');
        $body .= $renderer->render_list($missingimportmappingids);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Send notifications to admins, if the import mapping records could not get the status of completion.
     *
     * @param int $courseid Course id
     * @param array $incompletedeventids Incompleted mapping event ids
     */
    public static function notify_incompleted_import_mapping_records($courseid, $incompletedeventids) {
        global $DB, $PAGE;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('errorrestoremissingimportmapping_subj', 'block_opencast');
        $body = get_string('errorrestoreincompletedimportmapping_body', 'block_opencast', $a);

        // Add all backup eventids.
        $renderer = $PAGE->get_renderer('block_opencast');
        $body .= $renderer->render_list($incompletedeventids);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

    /**
     * Notify user about archiving an upload job.
     * @param int $courseid Course id
     * @param object $touser User to which notification is sent
     * @param string $title the title or filename of the video
     */
    public static function notify_archived_upload($courseid, $touser, $title) {
        global $DB;

        $a = (object)[
            'courseid' => $courseid,
            'coursefullname' => get_string('coursefullnameunknown', 'block_opencast'),
            'title' => $title,
        ];

        if ($course = $DB->get_record('course', ['id' => $courseid])) {
            $a->coursefullname = $course->fullname;
        }

        $subject = get_string('notificationuploadarchived_subj', 'block_opencast');
        $body = get_string('notificationuploadarchived_body', 'block_opencast', $a);

        self::send_message('opencasteventstatus_notification', $touser, $subject, $body);
    }
}
