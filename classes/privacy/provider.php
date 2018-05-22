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
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use \core_privacy\local\request\helper as request_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for block_opencast implementing provider.
 *
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {

    // This trait must be included.
    use \core_privacy\local\legacy_polyfill;

    /** Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     *
     * @return collection the updated collection of metadata items.
     */
    public static function _get_metadata(collection $collection) {
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
    public static function _get_contexts_for_userid($userid) {
        $contextlist = new \core_privacy\local\request\contextlist();

        // The block_opencast data is associated at the user context level, so retrieve the user's context id.
        $sql = "SELECT c.id
                  FROM {block_opencast_uploadjob} bo
                  JOIN {context} c ON c.instanceid = bo.userid AND c.contextlevel = :contextuser
                 WHERE bo.userid = :userid
              GROUP BY c.id";

        $params = [
            'contextuser'   => CONTEXT_USER,
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
    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;

        // If the user has block_opencast data, then only the User context should be present so get the first context.
        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        // Sanity check that context is at the User context level, then get the userid.
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $sql = "SELECT bo.id as id,
                       bo.courseid as courseid,
                       bo.fileid as fileid,
                       bo.status as status,
                       bo.timecreated as creation_time,
                       bo.timemodified as last_modified
                  FROM {block_opencast_uploadjob} bo
                 WHERE bo.userid = :userid
              ORDER BY bo.status";

        $params = [
            'userid' => $userid
        ];

        $jobs = $DB->get_records_sql($sql, $params);

        $data = (object) [
            'upload_jobs' => $jobs
        ];

        // The block_opencast data export is organised in: {User Context}/Opencast/data.json.
        $subcontext = [
            get_string('pluginname', 'block_opencast')
        ];

        writer::with_context($context)->export_data($subcontext, $data);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function _delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }

        $coursecontext = $context->get_course_context();
        $course = $coursecontext->instanceid;

        $DB->delete_records('block_opencast_uploadjob', ['courseid' => $course]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        // If the user has block_opencast data, then only the User context should be present so get the first context.
        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }
        $context = reset($contexts);

        // Sanity check that context is at the User context level, then get the userid.
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = $context->instanceid;

        $DB->delete_records('block_opencast_uploadjob', ['userid' => $userid]);
    }
}