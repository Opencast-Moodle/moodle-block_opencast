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
 * * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\upload_helper;

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/addvideo.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('edituploadjobs', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$block = $DB->get_record('block_instances', array('parentcontextid' => $coursecontext->id, 'blockname' => 'opencast'));
$blockcontext = context_block::instance($block->id);
$PAGE->set_context($blockcontext);

$data = new stdClass();
$options = array('subdirs' => 0,
                 'maxfiles' => -1,
                 'accepted_types' => 'video',
                 'return_types' => FILE_INTERNAL,
                 'maxbytes' => get_config('block_opencast', 'uploadfilelimit') );
file_prepare_standard_filemanager($data, 'videos', $options, $blockcontext, 'block_opencast', upload_helper::OC_FILEAREA, 0);

$addvideoform = new \block_opencast\local\addvideo_form(null, array('data' => $data, 'courseid' => $courseid));

if ($addvideoform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $addvideoform->get_data()) {
    file_save_draft_area_files($data->videos_filemanager, $blockcontext->id, 'block_opencast', upload_helper::OC_FILEAREA, 0, $options);
    // Update all upload jobs.
    \block_opencast\local\upload_helper::save_upload_jobs($courseid, $coursecontext);
    redirect($redirecturl, get_string('uploadjobssaved', 'block_opencast'));
}

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('edituploadjobs', 'block_opencast'));
$addvideoform->display();
echo $OUTPUT->footer();
