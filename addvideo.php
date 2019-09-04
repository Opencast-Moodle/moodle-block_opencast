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

// Use block context for this page to ignore course file upload limit.
$pagecontext = upload_helper::get_opencast_upload_context($courseid);
$PAGE->set_context($pagecontext);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('addvideo', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('addvideo', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$metadata_catalog = upload_helper::get_opencast_metadata_catalog();  

$addvideoform = new \block_opencast\local\addvideo_form(null, array('courseid' => $courseid, 'metadata_catalog' => $metadata_catalog));

if ($addvideoform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $addvideoform->get_data()) {

    // Record the user draft area in this context.
    $storedfile_presenter = $addvideoform->save_stored_file('video_presenter', $coursecontext->id, 'block_opencast', upload_helper::OC_FILEAREA, $data->video_presenter);
    $storedfile_presentation = $addvideoform->save_stored_file('video_presentation', $coursecontext->id, 'block_opencast', upload_helper::OC_FILEAREA, $data->video_presentation);

    if ($storedfile_presenter) {
        \block_opencast\local\file_deletionmanager::track_draftitemid($coursecontext->id, $data->video_presenter);
    }
    if ($storedfile_presentation) {
        \block_opencast\local\file_deletionmanager::track_draftitemid($coursecontext->id, $data->video_presentation);
    }

    $metadata = [];
    $get_title = true; //Make sure title (required) is added into metadata

    //Adding data into $metadata based on $metadata_catalog
    foreach ($metadata_catalog as $field) {
        $id = $field->name;
        if (array_key_exists($field->name, $data) AND $data->$id) {
            if ($field->name == 'title') { //Make sure the title is received!
                $get_title = false;
            }
            if ($field->name == 'subjects') {
                !is_array($data->$id) ? $data->$id = array($data->$id) : $data->$id = $data->$id;
            }
            $obj = [
                'id' => $id,
                'value' => $data->$id
            ];
            $metadata[] = $obj;
        }
    }

    //If admin forgets/mistakenly deletes the title from metadata_catalog the system will create a title!
    if ($get_title) {
        $title_obj = [
            'id' => 'title',
            'value' => $data->title ? $data->title : 'upload-task'
        ];
        $metadata[] = $title_obj;
    }
    $startDate = [
        'id' => 'startDate',
        'value' => date('Y-m-d', $data->startDate)
    ];
    $startTime = [
        'id' => 'startTime',
        'value' => date('H:i:s', $data->startDate).'Z'
    ];
    $metadata[] = $startDate;
    $metadata[] = $startTime;

    $options = new \stdClass();
    $options->metadata = json_encode($metadata);
    $options->presenter = $storedfile_presenter ? $storedfile_presenter->get_itemid() : '';
    $options->presentation = $storedfile_presentation ? $storedfile_presentation->get_itemid() : '';

    // Update all upload jobs.
    \block_opencast\local\upload_helper::save_upload_jobs($courseid, $coursecontext, $options);
    redirect($redirecturl, get_string('uploadjobssaved', 'block_opencast'));
}

$PAGE->requires->js_call_amd('block_opencast/block_form_handler','init');
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addvideo', 'block_opencast'));
$addvideoform->display();
echo $OUTPUT->footer();
