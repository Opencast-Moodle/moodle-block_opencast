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
 * @copyright  2020 Tim Schroeder, RWTH Aachen University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\upload_helper;
use tool_opencast\local\api;

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$attachment = optional_param('attachment', null, PARAM_ALPHANUMEXT);

$urlparams = ['video_identifier' => $identifier, 'courseid' => $courseid];
if ($attachment !== null) {
    $urlparams['attachment'] = $attachment;
}
$baseurl = new moodle_url('/blocks/opencast/addattachment.php', $urlparams);
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid]);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('addattachments', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$opencast = \block_opencast\local\apibridge::get_instance();

$attachmentfields = upload_helper::get_opencast_attachment_fields();
$addattachmentform = new \block_opencast\local\addattachment_form(null, ['attachmentfields' => $attachmentfields, 'courseid' => $courseid, 'identifier' => $identifier]);

if ($addattachmentform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $addattachmentform->get_data()) {
    $attachments = [];
    foreach ($attachmentfields as $attachmentfield) {
        $fieldname = $attachmentfield->name;
        if ($data->$fieldname) {

            // Upload attachment.
            $file = $addattachmentform->save_stored_file($fieldname, $coursecontext->id, 'block_opencast', upload_helper::OC_FILEAREA_ATTACHMENT, $data->$fieldname);
            if (!$file) {
                continue;
            }
            \block_opencast\local\file_deletionmanager::track_draftitemid($coursecontext->id, $data->$fieldname);
            $attachments[$fieldname] = $file->get_id();
        }
    }

    if (!empty($attachments)) {
        $opencast->add_attachments($identifier, $attachments, $courseid);
    }
    $msg = get_string('attachmentssaved', 'block_opencast');
    redirect($redirecturl, $msg);
}

$PAGE->requires->js_call_amd('block_opencast/block_form_handler', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addattachments', 'block_opencast'));
$addattachmentform->display();
echo $OUTPUT->footer();