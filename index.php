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

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/lib/tablelib.php');

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

$table = new flexible_table('opencast-videos-table');

$download = optional_param('download', '', PARAM_ALPHA);
if ($download) {
    $table->is_downloading($download, userdate(time(), '%Y-%m-%d-%H%M%S') . '_report');
}

$table->set_attribute('cellspacing', '0');
$table->set_attribute('cellpadding', '3');
$table->set_attribute('class', 'generaltable');
$table->set_attribute('id', 'opencast-videos-table');

$columns = array('start_date', 'title', 'published', 'workflow_state', 'action');
$headers = array('start_date', 'title', 'published', 'workflow_state', '');

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
$table->sortable(true, 'start_date', SORT_DESC);

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
$videodata = $opencast->get_course_videos($courseid, $table, $perpage, $download);

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();

if (has_capability('block/opencast:addvideo', $coursecontext)) {

    echo $OUTPUT->heading(get_string('uploadqueuetoopencast', 'block_opencast'));

    $videojobs = \block_opencast\local\upload_helper::get_upload_jobs($courseid);
    echo $renderer->render_upload_jobs($videojobs);

    $addvideourl = new moodle_url('/blocks/opencast/addvideo.php', array('courseid' => $courseid));
    $addvideobutton = $OUTPUT->single_button($addvideourl, get_string('edituploadjobs', 'block_opencast'));
    echo html_writer::div($addvideobutton);
}

echo $OUTPUT->heading(get_string('videosavailable', 'block_opencast'));

if ($videodata->error == 0) {

    foreach ($videodata->videos as $video) {

        $row = array();

        $row[] = $renderer->render_created($video->start);
        $row[] = $video->title;
        $row[] = $renderer->render_publication_status($video->publication_status);
        $row[] = $renderer->render_processing_state_icon($video->processing_state);

        if ($opencast->can_delete_acl_group_assignment($video)) {
            $row[] = $renderer->render_delete_acl_group_assignment_icon($courseid, $video->identifier);
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

