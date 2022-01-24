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
 * Autocompelete Suggestion helper.
 * @package    block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Autocompelete Suggestion helper.
 * @package    block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autocomplete_suggestion_helper {
    /**
     * Get all available suggestions for contributor and creator (Presenter) metadata field.
     *
     * @return array
     */
    public static function get_suggestions_for_creator_and_contributor($ocinstanceid) {
        // We gather all suggestions array lists from different methods and return it as one array.
        // We use array_unique to make sure that there is no duplication.
        // We use array_filter to make sure there is no empty elements.
        // We use array_merge to merge all arrays together.
        $suggestions = array_unique(
            array_filter(
                array_merge(
                    self::get_suggestions_from_course_teachers(),
                    self::get_suggestions_from_existing_uploadjobs(),
                    self::get_suggestions_from_opencast_course_videos($ocinstanceid)
                )
            )
        );

        return array_combine($suggestions, $suggestions);
    }

    /**
     * Get the fullname suggestions of the teachers in the course.
     *
     * @return array
     */
    private static function get_suggestions_from_course_teachers() {
        global $COURSE, $DB;

        // Initialize the array list to return.
        $suggestionlist = [];

        // Get the role of teachers.
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));

        $context = \context_course::instance($COURSE->id);
        // Get the teachers based on their role in the course context.
        $teachers = get_role_users($role->id, $context);

        // In case something went wrong, we return empty array.
        if (!empty($teachers)) {
            return $suggestionlist;
        }

        foreach ($teachers as $teacher) {
            $teacherfullname = fullname($teacher);
            // Check if the fullname is valid or if it is not already existed.
            if (!empty($teacherfullname) && !in_array($teacherfullname, $suggestionlist)) {
                $suggestionlist[] = $teacherfullname;
            }
        }

        // Finally, we return an array containing fullnames of course teachers.
        return $suggestionlist;
    }

    /**
     * Get the fullname suggestions from existing course uploadjobs and the metadata stored in moodle.
     *
     * @return array
     */
    private static function get_suggestions_from_existing_uploadjobs() {
        global $COURSE, $DB;

        // Initialize the array list to return.
        $suggestionlist = [];

        // Prepare the select sql with join.
        $sql = "SELECT uj.id, uj.userid, md.metadata FROM {block_opencast_uploadjob} uj "
            . "JOIN {block_opencast_metadata} md ON uj.id = md.uploadjobid "
            . "WHERE uj.courseid = :courseid";

        $params = [];
        $params['courseid'] = $COURSE->id;

        // Get records from db.
        $records = $DB->get_records_sql($sql, $params);

        // If no records, we return empty array.
        if (!$records) {
            return $suggestionlist;
        }

        foreach ($records as $record) {
            // At first, we consider the user who upload the video as an suggestion.

            // Get the user who performed the upload job.
            $uploadjobuser = \core_user::get_user($record->userid);

            // Get the fullname of the user.
            $uploadjobuserfullname = fullname($uploadjobuser);

            // Check if the fullname is valid or if it is not already existed.
            if (!empty($uploadjobuserfullname) && !in_array($uploadjobuserfullname, $suggestionlist)) {
                $suggestionlist[] = $uploadjobuserfullname;
            }

            // Next, we check the stored metadada to see if there is any creator or contributor fields.

            // Get metadata.
            $metadata = json_decode($record->metadata);

            // Check and return the creator field from stored metadada catalog.
            $creatorarray = array_filter($metadata, function ($v, $k) {
                return $v->id == 'creator';
            }, ARRAY_FILTER_USE_BOTH);

            // If there is creator field in stored metadada catalog.
            if (!empty($creatorarray)) {
                // Extract the creator object from filtered array.
                $creatormetadataobject = array_values($creatorarray)[0];

                // Merge the values of the object with the returned list.
                $suggestionlist = array_merge($suggestionlist, $creatormetadataobject->value);
            }

            // Check and return the contributor field from stored metadada catalog.
            $contributorarray = array_filter($metadata, function ($v, $k) {
                return $v->id == 'contributor';
            }, ARRAY_FILTER_USE_BOTH);

            // If there is contributor field in stored metadada catalog.
            if (!empty($contributorarray)) {
                // Extract the contributor object from filtered array.
                $contributormetadataobject = array_values($contributorarray)[0];

                // Merge the values of the object with the returned list.
                $suggestionlist = array_merge($suggestionlist, $contributormetadataobject->value);
            }
        }

        // Finally, we return the suggestion list.
        return $suggestionlist;
    }

    /**
     * Get the fullname suggestions from the available course opencat videos.
     *
     * @return array
     */
    private static function get_suggestions_from_opencast_course_videos($ocinstanceid) {
        global $COURSE;

        // Initialize the array list to return.
        $suggestionlist = [];

        // Get apibridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get course videos.
        $coursevidoes = $apibridge->get_course_videos($COURSE->id);

        // We return empty array in case there is an error.
        if ($coursevidoes->error != 0) {
            return $suggestionlist;
        }

        foreach ($coursevidoes->videos as $video) {
            // If the video object has presenters (creators), we merge them to the return array list.
            if (!empty($video->presenter)) {
                $suggestionlist = array_merge($suggestionlist, $video->presenter);
            }

            // If the video object has contributors, we merge them to the return array list.
            if (!empty($video->contributor)) {
                $suggestionlist = array_merge($suggestionlist, $video->contributor);
            }
        }

        // Finally, we return the suggestion list.
        return $suggestionlist;
    }
}
