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
 * Helper functions.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\error_manager;
use block_opencast\local\apibridge;
use block_opencast\local\series_form;
use tool_opencast\local\settings_api;
use tool_opencast\seriesmapping;

/**
 * Get icon mapping for FontAwesome.
 *
 * @return array
 */
function block_opencast_get_fontawesome_icon_map() {
    return [
        'block_opencast:share' => 'fa-share-square',
        'block_opencast:play' => 'fa-play-circle'
    ];
}

/**
 * Serve the create series form as a fragment.
 * @param object $args
 */
function block_opencast_output_fragment_series_form($args) {
    global $CFG, $USER, $DB;

    $args = (object)$args;
    $context = $args->context;
    $o = '';

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        parse_str($args->jsonformdata, $formdata);
        foreach ($formdata as $field => $value) {
            if ($value === "_qf__force_multiselect_submisstion") {
                $formdata[$field] = '';
            }
        }
    }

    list($ignored, $course) = get_context_info_array($context->id);

    require_capability('block/opencast:createseriesforcourse', $context);

    $metadatacatalog = json_decode(get_config('block_opencast', 'metadataseries_' . $args->ocinstanceid));
    // Make sure $metadatacatalog is array.
    $metadatacatalog = !empty($metadatacatalog) ? $metadatacatalog : [];
    if ($formdata) {
        $mform = new series_form(null, array('courseid' => $course->id,
            'ocinstanceid' => $args->ocinstanceid, 'metadata_catalog' => $metadatacatalog), 'post', '', null, true, $formdata);
        $mform->is_validated();
    } else if ($args->seriesid) {
        // Load stored series metadata.
        $apibridge = apibridge::get_instance($args->ocinstanceid);
        $ocseries = $apibridge->get_series_metadata($args->seriesid);
        $mform = new series_form(null, array('courseid' => $course->id, 'metadata' => $ocseries,
            'ocinstanceid' => $args->ocinstanceid, 'metadata_catalog' => $metadatacatalog));
    } else {
        // Get user series defaults when the page is new.
        $userdefaultsrecord = $DB->get_record('block_opencast_user_default', ['userid' => $USER->id]);
        $userdefaults = $userdefaultsrecord ? json_decode($userdefaultsrecord->defaults, true) : [];
        $userseriesdefaults = (!empty($userdefaults['series'])) ? $userdefaults['series'] : [];
        $mform = new series_form(null, array('courseid' => $course->id, 'ocinstanceid' => $args->ocinstanceid,
            'metadata_catalog' => $metadatacatalog, 'seriesdefaults' => $userseriesdefaults));
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();

    return $o;
}


/**
 * Pre-delete course hook to cleanup any records with references to the deleted course and delete unused series.
 *
 * @param stdClass $course The deleted course
 */
function block_opencast_pre_course_delete(\stdClass $course) {
    global $DB;

    foreach (settings_api::get_ocinstances() as $ocinstance) {
        $apibridge = apibridge::get_instance($ocinstance->id);
        foreach ($apibridge->get_course_series($course->id) as $courseseries) {
            // Don't delete series if there are any other courses using it.
            if ($DB->record_exists_sql('SELECT c.id FROM {tool_opencast_series} s
                        JOIN {course} c ON s.courseid = c.id
                        WHERE series = :series AND ocinstanceid = :instance AND s.courseid != :courseid',
                    ['series' => $courseseries->series, 'instance' => $ocinstance->id, 'courseid' => $course->id])) {
                error_manager::add_error(3, "Series $courseseries->series is in use elsewhere",
                        ['courseid' => $course->id, 'ocid' => $ocinstance->id, 'seriesid' => $courseseries->series]);
                continue;
            }
            $seriesvideos = $apibridge->get_series_videos($courseseries->series);
            if ($seriesvideos->error != 0) {
                error_manager::add_error(error_manager::DELETE_COURSE_COULD_NOT_RETRIEVE_SERIES,
                        "Could not retrieve series $courseseries->series, ocid $ocinstance->id.",
                        ['courseid' => $course->id, 'ocid' => $ocinstance->id, 'seriesid' => $courseseries->series]);
                mtrace("Could not retrieve series $courseseries->series, ocid $ocinstance->id.");
                continue;
            }
            foreach ($seriesvideos->videos as $video) {
                if (!$apibridge->trigger_delete_event($video->identifier)) {
                    error_manager::add_error(error_manager::DELETE_COURSE_COULD_NOT_DELETE_EVENT,
                            "Error deleting event $video->identifier, ocid $ocinstance->id.",
                            ['courseid' => $course->id, 'ocid' => $ocinstance->id, 'eventid' => $video->identifier]);
                    mtrace("Error deleting event $video->identifier, ocid $ocinstance->id.");
                }
            }
        }
    }

    $mappings = seriesmapping::get_records(array('courseid' => $course->id));
    foreach ($mappings as $mapping) {
        $mapping->delete();
    }
}
