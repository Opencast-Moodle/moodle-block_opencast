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
 * Overview of all videos in a series.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');

use block_opencast\local\apibridge;
use block_opencast\local\upload_helper;
use core\notification;
use mod_opencast\local\opencasttype;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $DB, $USER, $SITE;

$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);
$series = required_param('series', PARAM_ALPHANUMEXT);

$baseurl = new moodle_url('/blocks/opencast/overview_videos.php', ['ocinstanceid' => $ocinstanceid, 'series' => $series]);
$PAGE->set_url($baseurl);
$PAGE->set_context(context_system::instance());

require_login(get_course($SITE->id), false);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));

$apibridge = apibridge::get_instance($ocinstanceid);
$opencasterror = null;

if (settings_api::num_ocinstances() > 1) {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast') . ': ' . settings_api::get_ocinstance($ocinstanceid)->name);
} else {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
}

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

// Try to retrieve name from opencast.
$ocseries = $apibridge->get_series_by_identifier($series, true);

$hasaddvideopermission = false;
$hasviewpermission = false;
$hasdownloadpermission = false;
$hasdeletepermission = false;
$hasaccesspermission = false;

// Verify that user has permission to view series.
if (!$ocseries || !$apibridge->is_owner($ocseries->acl, $USER->id, $SITE->id)) {
    // User is not owner but might have read access to course where series is embedded.
    $records = $DB->get_records('tool_opencast_series', ['series' => $series, 'ocinstanceid' => $ocinstanceid]);
    foreach ($records as $record) {
        $coursecontext = context_course::instance($record->courseid, IGNORE_MISSING);
        if ($coursecontext) {
            if (has_capability('block/opencast:viewunpublishedvideos', $coursecontext)) {
                $hasviewpermission = true;
            }
            if (has_capability('block/opencast:addvideo', $coursecontext)) {
                $hasaddvideopermission = true;
            }
            if (has_capability('block/opencast:downloadvideo', $coursecontext)) {
                $hasdownloadpermission = true;
            }
            if (has_capability('block/opencast:deleteevent', $coursecontext)) {
                $hasdeletepermission = true;
            }
            if (has_capability('block/opencast:sharedirectaccessvideolink', $coursecontext)) {
                $hasaccesspermission = true;
            }
        }
    }

    if (!$hasviewpermission) {
        redirect(new moodle_url('/blocks/opencast/overview.php', ['ocinstanceid' => $ocinstanceid]),
            get_string('viewviedeosnotallowed', 'block_opencast'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

if ($apibridge->is_owner($ocseries->acl, $USER->id, $SITE->id)) {
    $hasviewpermission = true;
    $hasaddvideopermission = true;
    $hasdownloadpermission = true;
    $hasdeletepermission = true;
    $hasaccesspermission = true;
}

$isseriesowner = $ocseries && ($apibridge->is_owner($ocseries->acl, $USER->id, $SITE->id) ||
        !$apibridge->has_owner($ocseries->acl));

$PAGE->navbar->add(get_string('opencastseries', 'block_opencast'),
    new moodle_url('/blocks/opencast/overview.php', ['ocinstanceid' => $ocinstanceid]));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $baseurl);
echo $OUTPUT->header();

if ($ocseries) {
    echo $OUTPUT->heading($ocseries->title);
} else {
    // If that fails use id.
    echo $OUTPUT->heading($series);
}

echo html_writer::tag('p', get_string('uploadvideosexplanation', 'block_opencast') . '<br />' .
    get_string('uploadprocessingexplanation', 'block_opencast'));

// Show "Add video" button.
$addvideourl = new moodle_url('/blocks/opencast/addvideo.php', ['courseid' => $SITE->id,
    'ocinstanceid' => $ocinstanceid, 'intoseries' => $series, ]);
$addvideobutton = $OUTPUT->single_button($addvideourl, get_string('addvideo', 'block_opencast'), 'get');
echo html_writer::div($addvideobutton);

// Show "Add videos (batch)" button.
if (get_config('block_opencast', 'batchuploadenabled_' . $ocinstanceid)) {
    $batchuploadurl = new moodle_url('/blocks/opencast/batchupload.php',
        ['courseid' => $SITE->id, 'ocinstanceid' => $ocinstanceid, 'series' => $series]);
    $batchuploadbutton = $OUTPUT->single_button($batchuploadurl, get_string('batchupload', 'block_opencast'), 'get');
    echo html_writer::div($batchuploadbutton, 'opencast-batchupload-wrap');
}

// If there are upload jobs scheduled, show the upload queue table.
$videojobs = upload_helper::get_upload_jobs($ocinstanceid, $SITE->id);
if (count($videojobs) > 0) {
    // Show heading.
    echo $OUTPUT->heading(get_string('uploadqueuetoopencast', 'block_opencast'));

    // Show explanation.
    echo html_writer::tag('p', get_string('uploadqueuetoopencastexplanation', 'block_opencast'));
    echo $renderer->render_upload_jobs($ocinstanceid, $videojobs, true, 'overviewvideos', $series);
}

echo html_writer::tag('p', get_string('videosoverviewexplanation', 'block_opencast'));

// TODO handle opencast connection error. Break as soon as first error occurs.

// Build table.
$columns = ['owner', 'videos', 'linked', 'activities', 'action'];
$headers = [
    get_string('owner', 'block_opencast'),
    get_string('video', 'block_opencast'),
    get_string('embeddedasactivity', 'block_opencast'),
    get_string('embeddedasactivitywolink', 'block_opencast'),
    get_string('heading_actions', 'block_opencast'), ];
$table = $renderer->create_overview_videos_table('ignore', $headers, $columns, $baseurl);

$videos = $apibridge->get_series_videos($series)->videos;
$activityinstalled = core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
$showchangeownerlink = has_capability('block/opencast:viewusers', context_system::instance()) &&
    !empty(get_config('block_opencast', 'aclownerrole_' . $ocinstanceid));

foreach ($renderer->create_overview_videos_rows($videos, $apibridge, $ocinstanceid,
    $activityinstalled, $showchangeownerlink, false, $isseriesowner, $hasaddvideopermission,
    $hasdownloadpermission, $hasdeletepermission, '', $hasaccesspermission) as $row) {
    $table->add_data($row);
}

$table->finish_html();

if ($opencasterror) {
    notification::error($opencasterror);
}

echo $OUTPUT->footer();
