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

use block_opencast\local\activitymodulemanager;
use block_opencast\local\apibridge;
use core\notification;
use mod_opencast\local\opencasttype;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $DB, $USER, $SITE;

$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/overview.php', ['ocinstanceid' => $ocinstanceid]);
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
$myseries = [];

if (count($courses) > 0) {
    $courseids = array_column($courses, 'id');
    list($insql, $inparams) = $DB->get_in_or_equal($courseids);
    $sql = "SELECT id, series FROM {tool_opencast_series} WHERE courseid $insql AND ocinstanceid = ?";
    $inparams[] = $ocinstanceid;
    $myseries = array_column($DB->get_records_sql($sql, $inparams), 'series');
}

// Add series that are owned by the user.
$ownedseries = $apibridge->get_series_owned_by($USER->id);
$myseries = array_values(array_unique(array_merge($myseries, $ownedseries)));

// Build course table.
$columns = ['owner', 'series', 'linked', 'activities', 'videos'];
$headers = [
    get_string('owner', 'block_opencast'),
    get_string('series', 'block_opencast'),
    get_string('linkedinblock', 'block_opencast'),
    get_string('embeddedasactivity', 'block_opencast'),
    get_string('showvideos', 'block_opencast'), ];
$table = $renderer->create_series_courses_tables('ignore', $headers, $columns, $baseurl);
$sortcolumns = $table->get_sort_columns();

$activityinstalled = core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
$showchangeownerlink = has_capability('block/opencast:viewusers', context_system::instance()) &&
    !empty(get_config('block_opencast', 'aclownerrole_' . $ocinstanceid));

for ($i = $page * $perpage; $i < min(($page + 1) * $perpage, count($myseries)); $i++) {
    $row = [];

    // Try to retrieve name from opencast.
    $ocseries = $apibridge->get_series_by_identifier($myseries[$i], true);

    // Check if current user is owner of the series.
    if (in_array($myseries[$i], $ownedseries) || ($ocseries && !$apibridge->has_owner($ocseries->acl))) {
        if ($showchangeownerlink) {
            $row[] = html_writer::link(new moodle_url('/blocks/opencast/changeowner.php',
                ['ocinstanceid' => $ocinstanceid, 'identifier' => $myseries[$i], 'isseries' => true]),
                $OUTPUT->pix_icon('i/user', get_string('changeowner', 'block_opencast')));
        } else {
            $row[] = $OUTPUT->pix_icon('i/user', get_string('changeowner', 'block_opencast'));
        }

    } else {
        $row[] = '';
    }

    if ($ocseries) {
        $row[] = $ocseries->title;
    } else {
        // If that fails use id.
        $row[] = $myseries[$i];
    }

    $blocklinks = $DB->get_records('tool_opencast_series', ['ocinstanceid' => $ocinstanceid, 'series' => $myseries[$i]]);
    $blocklinks = array_column($blocklinks, 'courseid');

    $activitylinks = [];
    if ($activityinstalled) {
        $activitylinks = $DB->get_records('opencast', ['ocinstanceid' => $ocinstanceid,
            'opencastid' => $myseries[$i], 'type' => opencasttype::SERIES, ]);
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
                ['ocinstanceid' => $ocinstanceid, 'courseid' => $mc->id]),
                $mc->fullname);
        }

        if (in_array($course, $activitylinks)) {
            // Get activity.
            $moduleid = activitymodulemanager::get_module_for_series($ocinstanceid, $mc->id, $myseries[$i]);

            $rowactivities[] = html_writer::link(new moodle_url('/mod/opencast/view.php', ['id' => $moduleid]),
                $mc->fullname);
        }
    }

    $row[] = join("<br>", $rowblocks);
    $row[] = join("<br>", $rowactivities);
    $row[] = html_writer::link(new moodle_url('/blocks/opencast/overview_videos.php',
        ['ocinstanceid' => $ocinstanceid, 'series' => $myseries[$i]]),
        $OUTPUT->pix_icon('i/messagecontentvideo', get_string('showvideos', 'block_opencast')));

    $table->add_data($row);
}

$table->finish_html();
echo $OUTPUT->paging_bar(count($myseries), $page, $perpage, $baseurl);

// Show videos that the user owns but are not included in any of the series he has access to.
$ownedvideos = $apibridge->get_videos_owned_by($USER->id);
$ownedvideos = array_filter($ownedvideos->videos, function ($v) use ($myseries) {
    return !in_array($v->is_part_of, $myseries);
});

if (count($ownedvideos) > 0) {
    echo $OUTPUT->heading(get_string('ownedvideosoverview', 'block_opencast'), 2, ['mt-4']);
    echo html_writer::tag('p', get_string('ownedvideosoverview_explanation', 'block_opencast'));

    $columns = ['owner', 'videos', 'linked', 'activities', 'action'];
    $headers = [
        get_string('owner', 'block_opencast'),
        get_string('video', 'block_opencast'),
        get_string('embeddedasactivity', 'block_opencast'),
        get_string('embeddedasactivitywolink', 'block_opencast'),
        get_string('heading_actions', 'block_opencast'), ];
    $table = $renderer->create_overview_videos_table('ignore', $headers, $columns, $baseurl);

    $activityinstalled = core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
    $showchangeownerlink = course_can_view_participants(context_system::instance());

    foreach ($renderer->create_overview_videos_rows($ownedvideos, $apibridge, $ocinstanceid,
        $activityinstalled, $showchangeownerlink, true, false,
        true, true, true, 'overview', true) as $row) {
        $table->add_data($row);
    }

    $table->finish_html();
}

if ($opencasterror) {
    notification::error($opencasterror);
}

echo $OUTPUT->footer();
