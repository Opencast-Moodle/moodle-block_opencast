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

use block_opencast\local\series_form;

defined('MOODLE_INTERNAL') || die();

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
 */
function block_opencast_output_fragment_series_form($args) {
    global $CFG;

    $args = (object) $args;
    $context = $args->context;
    $o = '';

    $formdata = [];
    if(!empty($args->jsonformdata)) {
        $formdata = json_decode($args->jsonformdata, true);
    }

    list($ignored, $course) = get_context_info_array($context->id);

    require_capability('block/opencast:createseriesforcourse', $context);

    if($formdata['series']) {
        $mform =  new series_form(null, array('courseid' => $course->id, 'seriesid' => $formdata['series']));
    }
    else {
        $mform =  new series_form(null, array('courseid' => $course->id));
    }

    $mform->set_data($formdata);

    if(!empty($formdata)){
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();

    return $o;
}