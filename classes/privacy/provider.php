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
 * Privacy implementation for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for block_opencast implementing provider.
 *
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {

    /** Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     *
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('block_opencast_uploadjob', [
            'fileid' => 'privacy:metadata:block_opencast_uploadjob:fileid',
            'userid' => 'privacy:metadata:block_opencast_uploadjob:userid',
            'status' => 'privacy:metadata:block_opencast_uploadjob:status',
            'courseid' => 'privacy:metadata:block_opencast_uploadjob:courseid',
            'timecreated' => 'privacy:metadata:block_opencast_uploadjob:timecreated',
            'timemodified' => 'privacy:metadata:block_opencast_uploadjob:timemodified'
        ], 'privacy:metadata:block_opencast_uploadjob');

        // Uploads files to opencast.
        $collection->add_external_location_link('opencast', [
            'file' => 'privacy:metadata:opencast:file'
        ], 'privacy:metadata:opencast');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     *
     * @return contextlist $contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        // Since we can have only one block instance per course, we can use the course context.
        // Therefore we take each course, in which the user uploaded a video.
        $sql = "SELECT c.id
                  FROM {block_opencast_uploadjob} bo
                  JOIN {context} c ON c.instanceid = bo.courseid AND c.contextlevel = :contextcourse
                 WHERE bo.userid = :userid
              GROUP BY c.id";

        $params = [
            'contextcourse'   => CONTEXT_COURSE,
            'userid'        => $userid
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB, $PAGE;

        // If the user has block_opencast data, multiple course contexts can be returned.
        $contexts = $contextlist->get_contexts();

        $user = $contextlist->get_user();

        foreach ($contexts as $context) {

            // Sanity check that context is at the Course context level, then get the userid.
            if ($context->contextlevel !== CONTEXT_COURSE) {
                return;
            }

            $courseid = $context->instanceid;

            $sql = "SELECT bo.id as id,
                       bo.courseid as courseid,
                       f.filename as filename,
                       bo.status as status,
                       bo.timecreated as creation_time,
                       bo.timemodified as last_modified
                  FROM {block_opencast_uploadjob} bo JOIN
                       {files} f ON bo.fileid = f.id
                 WHERE bo.userid = :userid AND
                       bo.courseid = :courseid
              ORDER BY bo.status";

            $params = [
                'userid' => $user->id,
                'courseid' => $courseid
            ];

            $jobs = $DB->get_records_sql($sql, $params);

            // Rewrite status code to human readable string.
            $renderer = $PAGE->get_renderer('block_opencast');
            foreach ($jobs as $job) {
                $job->status = $renderer->render_status($job->status);
            }

            $data = (object)[
                'upload_jobs' => $jobs
            ];

            // The block_opencast data export is organised in: {Course Context}/Opencast/data.json.
            $subcontext = [
                get_string('pluginname', 'block_opencast')
            ];

            writer::with_context($context)->export_data($subcontext, $data)->export_area_files(
                $subcontext,
                'block_opencast',
                'videotoupload',
                0);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $course = $context->instanceid;

        $DB->delete_records('block_opencast_uploadjob', ['courseid' => $course]);

        // Delete all uploaded but not processed files.
        get_file_storage()->delete_area_files_select($context->id, 'block_opencast', 'videotoupload',
            " = 0");
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        // If the user has block_opencast data, multiple course contexts can be returned.
        $contexts = $contextlist->get_contexts();

        $user = $contextlist->get_user();

        foreach ($contexts as $context) {

            // Sanity check that context is at the Course context level.
            if ($context->contextlevel !== CONTEXT_COURSE) {
                return;
            }

            $courseid = $context->instanceid;

            $DB->delete_records('block_opencast_uploadjob',
                [
                    'userid' => $user->id,
                    'courseid' => $courseid
                ]);

            // Delete all uploaded but not processed files.
            get_file_storage()->delete_area_files_select($context->id, 'block_opencast', 'videotoupload',
                " = 0 AND userid = :userid", array('userid' => $user->id));
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_course::class)) {
            return;
        }

        $params = [
            'courseid'   => $context->instanceid
        ];

        // From uploadjobs.
        $sql = "SELECT bo.userid as userid
                  FROM {block_opencast_uploadjob} bo
                  WHERE bo.courseid = :courseid
              GROUP BY bo.userid";

        $userlist->add_from_sql('userid', $sql, $params);

    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!is_a($context, \context_course::class)) {
            return;
        }

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $context->instanceid], $userinparams);

        // Delete Upload jobs.
        $DB->delete_records_select('block_opencast_uploadjob',
            "courseid = :courseid AND userid {$userinsql}", $params);

        // Delete all uploaded but not processed files.
        get_file_storage()->delete_area_files_select($context->id, 'block_opencast', 'videotoupload',
            " = 0 AND userid {$userinsql}", $userinparams);
    }
}