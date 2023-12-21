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

use block_opencast\local\apibridge;
use block_opencast\local\series_form;
use tool_opencast\seriesmapping;

/**
 * Get icon mapping for FontAwesome.
 *
 * @return array
 */
function block_opencast_get_fontawesome_icon_map() {
    return [
        'block_opencast:share' => 'fa-share-square',
        'block_opencast:play' => 'fa-play-circle',
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
        $mform = new series_form(null, ['courseid' => $course->id,
            'ocinstanceid' => $args->ocinstanceid, 'metadata_catalog' => $metadatacatalog, ], 'post', '', null, true, $formdata);
        $mform->is_validated();
    } else if ($args->seriesid) {
        // Load stored series metadata.
        $apibridge = apibridge::get_instance($args->ocinstanceid);
        $ocseries = $apibridge->get_series_metadata($args->seriesid);
        $mform = new series_form(null, ['courseid' => $course->id, 'metadata' => $ocseries,
            'ocinstanceid' => $args->ocinstanceid, 'metadata_catalog' => $metadatacatalog, ]);
    } else {
        // Get user series defaults when the page is new.
        $userdefaultsrecord = $DB->get_record('block_opencast_user_default', ['userid' => $USER->id]);
        $userdefaults = $userdefaultsrecord ? json_decode($userdefaultsrecord->defaults, true) : [];
        $userseriesdefaults = (!empty($userdefaults['series'])) ? $userdefaults['series'] : [];
        $mform = new series_form(null, ['courseid' => $course->id, 'ocinstanceid' => $args->ocinstanceid,
            'metadata_catalog' => $metadatacatalog, 'seriesdefaults' => $userseriesdefaults, ]);
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();

    return $o;
}


/**
 * Pre-delete course hook to cleanup any records with references to the deleted course.
 *
 * @param stdClass $course The deleted course
 */
function block_opencast_pre_course_delete(stdClass $course) {
    $mappings = seriesmapping::get_records(['courseid' => $course->id]);
    foreach ($mappings as $mapping) {
        $mapping->delete();
    }
}


/**
 * Pre-delete block hook to show a confirmation message or to perform cleaup the related series and videos.
 *
 * @param object $instance a row from the block_instances table
 *
 * @throws moodle_exception
 */
function block_opencast_pre_block_delete($instance) {

    // We make sure if the deleting block is Opencast block, otherwise we do nothing!
    if ($instance->blockname !== 'opencast') {
        return;
    }

    // Get the course and context base don block instance parentcontextid.
    list($context, $course, $cm) = get_context_info_array($instance->parentcontextid);

    // We get the flag 'removeseriesmapping' to decide whether to delete the series mapping.
    $removeseriesmapping = optional_param('removeseriesmapping', null, PARAM_INT);

    // At first the flag is not set, therefore we have the chance to show a confirmation page.
    if (is_null($removeseriesmapping) && !empty($course) && !empty($context)) {
        $deletepage = new moodle_page();
        $deletepage->set_pagelayout('admin');
        $deletepage->blocks->show_only_fake_blocks(true);
        $deletepage->set_course($course);
        $deletepage->set_context($context);
        if ($cm) {
            $deletepage->set_cm($cm);
        }
        // The default delete url.
        $deleteurl = new moodle_url('/course/view.php',
            ['id' => $course->id, 'sesskey' => sesskey(), 'bui_deleteid' =>  $instance->id, 'bui_confirm' => 1]
        );
        $deletepage->set_url($deleteurl);
        $deletepage->set_block_actions_done();
        $PAGE = $deletepage;
        /** @var core_renderer $output */
        $output = $deletepage->get_renderer('core');
        $OUTPUT = $output;

        $deletepagetitle = get_string('confirm_delete_seriesmapping_title_page', 'block_opencast');
        $confirmtitle = get_string('confirm_delete_seriesmapping_title_confirm', 'block_opencast');
        $message = get_string('confirm_delete_seriesmapping_content_confirm', 'block_opencast');

        $PAGE->navbar->add($deletepagetitle);
        $PAGE->set_title($deletepagetitle);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        // Creating a confirmation page with assigning a flag 'removeseriesmapping' to decide whether to delete series mapping.
        $confirmurl = new moodle_url($deletepage->url, array('removeseriesmapping' => 1));
        $cancelurl = new moodle_url($deletepage->url,  array('removeseriesmapping' => 0));

        $yesbutton = new single_button($confirmurl, get_string('yes'));
        $nobutton = new single_button($cancelurl, get_string('no'));

        $displayoptions['confirmtitle'] = $confirmtitle;

        echo $OUTPUT->confirm($message, $yesbutton, $nobutton, $displayoptions);
        echo $OUTPUT->footer();
        // Make sure that nothing else happens after we have displayed this form.
        exit;
    } else if ($removeseriesmapping === 1) { // We only perform the series mapping deletion if the flag is set to 1.
        $success = true;
        $mappings = seriesmapping::get_records(['courseid' => $course->id]);
        foreach ($mappings as $mapping) {
            if (!$mapping->delete()) {
                $success = false;
            }
        }
        if (!$success) {
            throw new moodle_exception('error_block_delete_seriesmapping', 'block_opencast');
        }
    }
    // we let the process continue if the flag 'removeseriesmapping' is set to 0,
    // which means it is decided not to delete series mapping.
}
