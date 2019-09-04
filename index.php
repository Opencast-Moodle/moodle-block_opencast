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
 * Page overview.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');
use block_opencast\local\apibridge;

global $PAGE, $OUTPUT, $CFG;

$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('overview', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:viewunpublishedvideos', $coursecontext);

$table = new block_opencast\local\flexible_table('opencast-videos-table');

$download = optional_param('download', '', PARAM_ALPHA);
if ($download) {
    $table->is_downloading($download, userdate(time(), '%Y-%m-%d-%H%M%S') . '_report');
}

$table->set_attribute('cellspacing', '0');
$table->set_attribute('cellpadding', '3');
$table->set_attribute('class', 'generaltable');
$table->set_attribute('id', 'opencast-videos-table');

$apibridge = apibridge::get_instance();
$toggleaclroles = (count($apibridge->getroles(array('permanent' => 0))) !== 0) &&
    (get_config('block_opencast', 'workflow_roles') != "");

if ($toggleaclroles && get_config('block_opencast', 'showpublicationchannels')) {
    $columns = array('start_date', 'end_date', 'title', 'location', 'published', 'workflow_state', 'visibility', 'action');
    $headers = array('start_date', 'end_date', 'title', 'location', 'published', 'workflow_state', 'visibility', '');
} else if ($toggleaclroles && !get_config('block_opencast', 'showpublicationchannels')) {
    $columns = array('start_date', 'end_date', 'title', 'location', 'workflow_state', 'visibility', 'action');
    $headers = array('start_date', 'end_date', 'title', 'location', 'workflow_state', 'visibility', '');
} else if (!$toggleaclroles && get_config('block_opencast', 'showpublicationchannels')) {
    $columns = array('start_date', 'end_date', 'title', 'location', 'published', 'workflow_state', 'action');
    $headers = array('start_date', 'end_date', 'title', 'location', 'published', 'workflow_state', '');
} else {
    $columns = array('start_date', 'end_date', 'title', 'location', 'workflow_state', 'action');
    $headers = array('start_date', 'end_date', 'title', 'location', 'workflow_state', '');
}

foreach ($headers as $i => $header) {
    if (!empty($header)) {
        $headers[$i] = get_string('h' . $header, 'block_opencast');
    } else {
        $headers[$i] = '';
    }
}

$table->headers = $headers;
$table->define_columns($columns);
$table->define_baseurl($baseurl);

$table->no_sorting('action');
$table->no_sorting('published');
$table->sortable(true, "start_date");

$table->pageable(true);
$table->is_downloadable(false);

$table->set_control_variables(
    array(
        TABLE_VAR_SORT => 'tsort',
        TABLE_VAR_PAGE => 'page'
    )
);

$table->setup();

$perpage = optional_param('perpage', 20, PARAM_INT);

$opencast = \block_opencast\local\apibridge::get_instance();
$sortcolumns = $table->get_sort_columns();
$videodata = $opencast->get_course_videos($courseid, $sortcolumns);

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();


// Check if series exists in OC system. Show a error otherwise.

$seriesid = $apibridge->get_stored_seriesid($courseid);
$ocseriesid = $apibridge->get_course_series($courseid);

if ($seriesid && !$ocseriesid) {
    if (has_capability('block/opencast:defineseriesforcourse', $coursecontext)) {
        echo $OUTPUT->notification(get_string('series_does_not_exist_admin', 'block_opencast', $seriesid));
    } else {
        echo $OUTPUT->notification(get_string('series_does_not_exist', 'block_opencast'));
    }
}

echo $renderer->render_series_settings_actions($courseid,
    !$apibridge->get_stored_seriesid($courseid) && has_capability('block/opencast:createseriesforcourse', $coursecontext),
    has_capability('block/opencast:defineseriesforcourse', $coursecontext));

if (has_capability('block/opencast:addvideo', $coursecontext)) {

    echo $OUTPUT->heading(get_string('uploadqueuetoopencast', 'block_opencast'));

    $videojobs = \block_opencast\local\upload_helper::get_upload_jobs($courseid);
    echo $renderer->render_upload_jobs($videojobs);

    $addvideourl = new moodle_url('/blocks/opencast/addvideo.php', array('courseid' => $courseid));
    $addvideobutton = $OUTPUT->single_button($addvideourl, get_string('addvideo', 'block_opencast'));
    echo html_writer::div($addvideobutton);
}

echo $OUTPUT->heading(get_string('videosavailable', 'block_opencast'));

if ($videodata->error == 0) {

    $deletedvideos = $DB->get_records("block_opencast_deletejob", array(), "", "opencasteventid");

    foreach ($videodata->videos as $video) {

        $row = array();

        $row[] = $renderer->render_created($video->start);
        if ($video->duration) {
            $row[] = userdate(strtotime($video->start) + intdiv($video->duration, 1000),
                get_string('strftimedatetime', 'langconfig'));
        } else {
            $row[] = "";
        }

        $row[] = $video->title;
        $row[] = $video->location;

        if (get_config('block_opencast', 'showpublicationchannels')) {
            $row[] = $renderer->render_publication_status($video->publication_status);
        }

        if (array_key_exists($video->identifier, $deletedvideos)) {

            $row[] = $renderer->render_processing_state_icon("DELETING");
            $row[] = "";

        } else {

            $row[] = $renderer->render_processing_state_icon($video->processing_state);

            if ($toggleaclroles) {
                if ($video->processing_state !== "SUCCEEDED" && $video->processing_state !== "FAILED" && $video->processing_state !== "STOPPED") {
                    $row[] = "-";
                } else {
                    $visible = $apibridge->is_event_visible($video->identifier, $courseid);
                    $row[] = $renderer->render_change_visibility_icon($courseid, $video->identifier, $visible);
                }
            }

            $actions = '';
            if ($opencast->can_delete_acl_group_assignment($video, $courseid)) {
                $actions .= $renderer->render_delete_acl_group_assignment_icon($courseid, $video->identifier);
            }

            // in order to add metadata config
            if ($opencast->can_update_event_metadata($video, $courseid)) {
                $actions .= $renderer->render_update_metadata_event_icon($courseid, $video->identifier);
            }

            if ($opencast->can_delete_event_assignment($video, $courseid)) {
                $actions .= $renderer->render_delete_event_icon($courseid, $video->identifier);
            }

            $row[] = $actions;
        }

        $table->add_data($row);
    }
} else {
    echo html_writer::div(get_string('errorgetblockvideos', 'block_opencast', $videodata->error), 'opencast-bc-wrap');
}

if ($download) {
    $table->finish_output();
} else {
    $table->finish_html();
    echo $OUTPUT->footer();
}

