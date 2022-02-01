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
 * Overview of all series.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');
require_once($CFG->dirroot . '/course/lib.php');

use block_opencast\local\apibridge;
use mod_opencast\local\opencasttype;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $DB, $USER, $SITE;

$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/overview.php', array('ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);
$PAGE->set_context(context_system::instance());

require_login(get_course($SITE->id), false);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));

$courses = get_user_capability_course('block/opencast:viewunpublishedvideos');

$apibridge = apibridge::get_instance($ocinstanceid);
$opencasterror = null;

if (settings_api::num_ocinstances() > 1) {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast') . ': ' . settings_api::get_ocinstance($ocinstanceid)->name);
} else {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
}

$PAGE->navbar->add(get_string('opencastseries', 'block_opencast'), $baseurl);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('seriesoverview', 'block_opencast'));
echo html_writer::tag('p', get_string('seriesoverviewexplanation', 'block_opencast'));

// TODO handle opencast connection error. Break as soon as first error occurs.

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');
$myseries = array();

foreach ($courses as $course) {
    $courseseries = $DB->get_records('tool_opencast_series', array('courseid' => $course->id, 'ocinstanceid' => $ocinstanceid));

    foreach ($courseseries as $series) {
        $myseries[] = $series->series;
    }
}

// Add series that are owned by the user.
$ownedseries = $apibridge->get_series_owned_by($USER->id);
$myseries = array_unique(array_merge($myseries, $ownedseries));

// Build course table.
$columns = array('owner', 'series', 'linked', 'activities', 'videos');
$headers = array(
    get_string('owner', 'block_opencast'),
    get_string('series', 'block_opencast'),
    get_string('linkedinblock', 'block_opencast'),
    get_string('embeddedasactivity', 'block_opencast'),
    get_string('showvideos', 'block_opencast'));
$table = $renderer->create_series_courses_tables('ignore', $headers, $columns, $baseurl);
$sortcolumns = $table->get_sort_columns();

$activityinstalled = \core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
$showchangeownerlink = course_can_view_participants(context_system::instance());

foreach ($myseries as $seriesid) {
    $row = array();

    // Check if current user is owner of the series.
    if (in_array($seriesid, $ownedseries)) {
        if ($showchangeownerlink) {
            $row[] = html_writer::link(new moodle_url('/blocks/opencast/changeowner.php',
                array('ocinstanceid' => $ocinstanceid, 'identifier' => $seriesid, 'isseries' => true)),
                $OUTPUT->pix_icon('i/user', get_string('changeowner', 'block_opencast')));
        } else {
            $row[] = $OUTPUT->pix_icon('i/user', get_string('changeowner', 'block_opencast'));
        }

    } else {
        $row[] = '';
    }

    // Try to retrieve name from opencast.
    $ocseries = $apibridge->get_series_by_identifier($seriesid);
    if ($ocseries) {
        $row[] = $ocseries->title;
    } else {
        // If that fails use id.
        $row[] = $seriesid;
    }

    $blocklinks = $DB->get_records('tool_opencast_series', array('ocinstanceid' => $ocinstanceid, 'series' => $seriesid));
    $blocklinks = array_column($blocklinks, 'courseid');

    $activitylinks = array();
    if ($activityinstalled) {
        $activitylinks = $DB->get_records('opencast', array('ocinstanceid' => $ocinstanceid,
            'opencastid' => $seriesid, 'type' => opencasttype::SERIES));
        $activitylinks = array_column($activitylinks, 'course');
    }

    $courses = array_unique(array_merge($blocklinks, $activitylinks));

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
                $mc->fullname);
        }

        if (in_array($course, $activitylinks)) {
            // Get activity.
            $moduleid = \block_opencast\local\activitymodulemanager::get_module_for_series($ocinstanceid, $mc->id, $seriesid);

            $rowactivities[] = html_writer::link(new moodle_url('/mod/opencast/view.php', array('id' => $moduleid)),
                $mc->fullname);
        }
    }

    $row[] = join("<br>", $rowblocks);
    $row[] = join("<br>", $rowactivities);
    $row[] = html_writer::link(new moodle_url('/blocks/opencast/overview_videos.php', array('ocinstanceid' => $ocinstanceid, 'series' => $seriesid)),
        $OUTPUT->pix_icon('i/messagecontentvideo', get_string('showvideos', 'block_opencast')));

    $table->add_data($row);
}

$table->finish_html();

// todo Retrieve videos that are owned but not included in any series.

if ($opencasterror) {
    \core\notification::error($opencasterror);
}

echo $OUTPUT->footer();