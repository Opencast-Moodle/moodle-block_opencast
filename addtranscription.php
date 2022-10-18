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
 * Add new transcription to the event
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\apibridge;
use block_opencast\local\attachment_helper;

global $PAGE, $OUTPUT, $CFG, $SITE;

require_once($CFG->dirroot . '/repository/lib.php');

$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$indexurl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$redirecturl = new moodle_url('/blocks/opencast/managetranscriptions.php',
    array('video_identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$baseurl = new moodle_url('/blocks/opencast/addtranscriptions.php',
    array('video_identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $indexurl);
$PAGE->navbar->add(get_string('managetranscriptions', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('addnewtranscription', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$video = $apibridge->get_opencast_video($identifier);
if ($video->error || $video->video->processing_state != 'SUCCEEDED' ||
    empty(get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid))) {
    redirect($redirecturl, get_string('unabletoaddnewtranscription', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
}

$addtranscriptionform = new \block_opencast\local\addtranscription_form(null,
        array('courseid' => $courseid, 'identifier' => $identifier, 'ocinstanceid' => $ocinstanceid));

if ($addtranscriptionform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $addtranscriptionform->get_data()) {
    $storedfile = $addtranscriptionform->save_stored_file('transcription_file', $coursecontext->id,
                        'block_opencast', attachment_helper::OC_FILEAREA_ATTACHMENT, $data->transcription_file);
    $flavor = $data->transcription_flavor;
    if (isset($storedfile) && $storedfile && !empty($flavor)) {
        $success = attachment_helper::upload_single_transcription($storedfile, $flavor, $ocinstanceid, $identifier);
        $message = get_string('transcriptionuploadsuccessed', 'block_opencast');
        $status = \core\output\notification::NOTIFY_SUCCESS;
        if (!$success) {
            $message = get_string('transcriptionuploadfailed', 'block_opencast');
            $status = \core\output\notification::NOTIFY_ERROR;
        }
        attachment_helper::remove_single_transcription_file($storedfile->get_itemid());
        redirect($redirecturl, $message, null, $status);
    } else {
        redirect($redirecturl, get_string('missingtranscriptionuploadparams', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addnewtranscription', 'block_opencast'));
$addtranscriptionform->display();
echo $OUTPUT->footer();
