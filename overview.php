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
 * Overview of all series and videos.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');

use block_opencast\local\apibridge;
use tool_opencast\local\settings_api;


// TODO nach serien kategorisieren

global $PAGE, $OUTPUT, $CFG, $DB, $USER;

$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/overview.php', array('ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login();

$courses = get_user_capability_course('block/opencast:viewunpublishedvideos');

// TODO maybe display all oc instances on one page.

$apibridge = apibridge::get_instance($ocinstanceid);
$opencasterror = null;

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));

if (settings_api::num_ocinstances() > 1) {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast') . ': ' . settings_api::get_ocinstance($ocinstanceid)->name);
} else {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
}

// TODO change string.
$PAGE->navbar->add(get_string('overview', 'block_opencast'), $baseurl);
echo $OUTPUT->header();

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');


echo $OUTPUT->heading(get_string('videosavailable', 'block_opencast'));

// TODO handle opencast connection error. Break as soon as first error occurs.

$myseries = array();

foreach ($courses as $course) {
    $courseseries = $DB->get_records('tool_opencast_series', array('courseid' => $course->id, 'ocinstanceid' => $ocinstanceid));

    foreach ($courseseries as $series) {
        if (!array_key_exists($series->series, $myseries)) {
            $myseries[$series->series] = array('courses' => array(), 'activities' => array());
        }

        if (!in_array($course->id, $myseries[$series->series]['courses'])) {
            $myseries[$series->series]['courses'][] = $course->id;
        }
    }
}

$cards = array();
$idx = 0;
foreach ($myseries as $seriesid => $info) {
    // Try to retrieve name from opencast.
    $ocseries = $apibridge->get_series_by_identifier($seriesid);
    if ($ocseries) {
        $heading = $ocseries->title;
    } else {
        // If that fails use id.
        $heading = $seriesid;
    }

    // Build course table.
    $columns = array('course', 'linked', 'activities');
    $headers = array('course', 'linked', 'activities');
    $table = $renderer->create_series_courses_tables('ignore', $headers, $columns, $baseurl);
    $sortcolumns = $table->get_sort_columns();

    foreach ($info['courses'] as $course) {
        $row = array();
        $row['course'] = 'Course title';
        $row['linked'] = 'true';
        $row['activities'] = 'true';
        $table->add_data($row);
    }

    $table->finish_html();

    $cards[] = array('id' => $idx, 'heading' => $heading, 'text' => 'more test');
    $idx++;
}

echo $renderer->render_series_course_overview('seriesaccordion', $cards);

if ($opencasterror) {
    \core\notification::error($opencasterror);
}

echo $OUTPUT->footer();