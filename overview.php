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
use mod_opencast\local\opencasttype;
use tool_opencast\local\settings_api;


// TODO nach serien kategorisieren

global $PAGE, $OUTPUT, $CFG, $DB, $USER;

$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$usercontext = context_user::instance($USER->id); // todo No clue if user context is correct.
$baseurl = new moodle_url('/blocks/opencast/overview.php', array('ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);
$PAGE->set_context($usercontext);

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


echo $OUTPUT->heading('My Opencast Series'); //todo string

// TODO handle opencast connection error. Break as soon as first error occurs.

$myseries = array();

foreach ($courses as $course) {
    $courseseries = $DB->get_records('tool_opencast_series', array('courseid' => $course->id, 'ocinstanceid' => $ocinstanceid));

    foreach ($courseseries as $series) {
        $myseries[] = $series->series;
    }
}

// Build course table.
$columns = array('series', 'linked', 'activities', 'videos');
$headers = array('Series', 'Linked in block', 'Embedded as activity', 'View videos'); // TODO strings
$table = $renderer->create_series_courses_tables('ignore', $headers, $columns, $baseurl);
$sortcolumns = $table->get_sort_columns();

foreach ($myseries as $seriesid) {
    $row = array();

    // Try to retrieve name from opencast.
    $ocseries = $apibridge->get_series_by_identifier($seriesid);
    if ($ocseries) {
        $row[] = $ocseries->title;
    } else {
        // If that fails use id.
        $row[] = $seriesid;
    }

    # todo on index block show number of series and videos (in block on myoverview page)

    $blocklinks = $DB->get_records('tool_opencast_series', array('ocinstanceid' => $ocinstanceid, 'series' => $seriesid));
    $blocklinks = array_column($blocklinks, 'courseid');
    $activitylinks = $DB->get_records('opencast', array('ocinstanceid' => $ocinstanceid,
        'opencastid' => $seriesid, 'type' => opencasttype::SERIES)); // TODO check if mod installed
    $activitylinks = array_column($activitylinks, 'course');
    $courses = array_unique(array_merge($blocklinks, $activitylinks));

    // TODO delete entry in tool_series if course is deleted

    // TODO mod opencast error without block instance
    // TODO entries are not deleted from mod opencast table when activities are deleted

    // TODO check this coures loop if depended on block linked

    $rowblocks = [];
    $rowactivities = [];

    foreach ($courses as $course) {
        try {
            $mc = get_course($course);
        } catch (dml_missing_record_exception $ex) {
            continue;
        }

        if (in_array($course, $blocklinks)) {
            $rowblocks[] = html_writer::link(new moodle_url('/blocks/opencast/index.php',
                array('ocinstanceid' => $ocinstanceid, 'courseid' => $mc->id)),
                $mc->fullname, array('target' => '_blank'));
        }

        if (in_array($course, $activitylinks)) {
            // Get activity.
            $moduleid = \block_opencast\local\activitymodulemanager::get_module_for_series($ocinstanceid, $mc->id, $seriesid);

            $rowactivities[] = html_writer::link(new moodle_url('/mod/opencast/view.php', array('id' => $moduleid)),
                $mc->fullname, array('target' => '_blank'));
        }
    }

    $row[] = join("<br>", $rowblocks);
    $row[] = join("<br>", $rowactivities);
    $row[] = html_writer::link(new moodle_url('/blocks/opencast/overview_videos.php', array('ocinstanceid' => $ocinstanceid, 'series' => $seriesid)),
        'Videos'); // TODO icon, string
    $table->add_data($row);
}

$table->finish_html();


# echo $renderer->render_series_course_overview('seriesaccordion', $cards);

if ($opencasterror) {
    \core\notification::error($opencasterror);
}

echo $OUTPUT->footer();