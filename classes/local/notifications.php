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

defined('MOODLE_INTERNAL') || die();

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
     * @param string $messagetype
     * @param object $touser
     * @param string $subject
     * @param string $body
     * @param \stored_file $attachment
     * @param object $fromuser
     */
    private static function send_message($messagetype, $touser, $subject, $body) {

        $message = new \core\message\message();
        $message->courseid = SITEID;
        $message->component = 'block_opencast';
        $message->name = $messagetype;
        $message->userfrom = \core_user::get_user(\core_user::NOREPLY_USER);
        $message->userto = $touser;
        $message->subject = $subject;
        $message->fullmessage = html_to_text($body);
        $message->fullmessageformat = FORMAT_PLAIN;
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

        $a = (object) [
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
     * @param int $courseid
     */
    public static function notify_missing_events($courseid, $missingevents) {
        global $DB, $PAGE;

        $a = (object) [
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
     * @param \Exception $e
     */
    public static function notify_error($identifier, \Exception $e = null) {

        $subject = get_string('erroremailsubj', 'block_opencast');

        $message = empty($e) ? '' : $e->getMessage();
        $errorstr = get_string($identifier, 'block_opencast', $identifier);
        $a = (object) [
            'message' => $message,
            'errorstr' => $errorstr
        ];

        $body = get_string('erroremailbody', 'block_opencast', $a);

        $admin = get_admin();
        self::send_message('error', $admin, $subject, $body);
    }

}
