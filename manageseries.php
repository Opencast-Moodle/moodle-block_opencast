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
 *
 * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\persistent;
use tool_opencast\seriesmapping;

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $USER, $DB;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$coursecontext = context_course::instance($courseid);

$baseurl = new moodle_url('/blocks/opencast/manageseries.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$PAGE->requires->js_call_amd('block_opencast/block_manage_series', 'init', [$coursecontext->id, 'seriesinput']);
$PAGE->requires->css('/blocks/opencast/css/tabulator.min.css');
$PAGE->requires->css('/blocks/opencast/css/tabulator_bootstrap4.min.css');


$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('editseriesforcourse', 'block_opencast'), $baseurl);

// Capability check.
require_capability('block/opencast:defineseriesforcourse', $coursecontext);

$editseriesform = new \block_opencast\local\editseries_form(null, array('courseid' => $courseid));

$apibridge = \block_opencast\local\apibridge::get_instance();

if ($editseriesform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $editseriesform->get_data()) {
    $allseries = json_decode($data->seriesinput);

    $numdefault = 0;

    // First check that all series are valid.
    foreach($allseries as $seriesid => $isdefault) {
        if(!$apibridge->ensure_series_is_valid($seriesid)) {
            redirect($baseurl, get_string('seriesidnotvalid', 'block_opencast', $seriesid), null, \core\output\notification::NOTIFY_ERROR);
        }
        if($isdefault) {
            $numdefault += 1;
        }
    }

    // Ensure only one default series is given.
    if($numdefault > 1 || $numdefault === 0 && !empty($allseries)) {
        redirect($baseurl, get_string('seriesonedefault', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
    }

    // Now update database.
    foreach($allseries as $seriesid => $isdefault) {
        // todo write method
        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'series' => $seriesid));

        if($mapping) {
            if(intval($mapping->get('isdefault')) != $isdefault) {
                $mapping->set('isdefault', $isdefault);
                $mapping->update();
            }
        }
        else {
            $mapping = new seriesmapping();
            $mapping->set('courseid', $courseid);
            $mapping->set('series', $seriesid);
            $mapping->set('isdefault', $isdefault);
            $mapping->create();
        }


        // todo update acls

    }

    $courseseries = $DB->get_records('tool_opencast_series', array('courseid' => $courseid));
    $tobedeleted = array_diff(array_column($courseseries, 'series'), array_keys(get_object_vars($allseries)));

    foreach($tobedeleted as $series) {
        $mapping = seriesmapping::get_record(array('courseid' => $courseid, 'series' => $series), true);

        if ($mapping) {
            $mapping->delete();
        }
    }

/*
    if ($data->seriesid) {
        if ($apibridge->ensure_series_is_valid($data->seriesid)) {
            $apibridge->update_course_series($courseid, $data->seriesid, $USER->id);
            // Update course series.
            redirect($redirecturl, get_string('seriesidsaved', 'block_opencast'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            // Invalid series id.
            redirect($redirecturl, get_string('seriesidnotvalid', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        $apibridge->unset_course_series($courseid);
        redirect($redirecturl, get_string('seriesidunset', 'block_opencast'), null, \core\output\notification::NOTIFY_SUCCESS);

    }
*/
}

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editseriesforcourse', 'block_opencast'));

// TODO maybe render from template?!
echo $renderer->render_manage_series_table();

$editseriesform->display();

echo $OUTPUT->footer();
