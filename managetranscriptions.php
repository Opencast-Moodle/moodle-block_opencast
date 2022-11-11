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
 * Manage event's transcriptions
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\apibridge;

global $PAGE, $OUTPUT, $CFG, $SITE;

require_once($CFG->dirroot . '/repository/lib.php');

$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$baseurl = new moodle_url('/blocks/opencast/managetranscriptions.php',
    array('video_identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('managetranscriptions', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$video = $apibridge->get_opencast_video($identifier);
if ($video->error || $video->video->processing_state != 'SUCCEEDED' ||
    empty(get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid))) {
    redirect($redirecturl,
        get_string('unabletomanagetranscriptions', 'block_opencast'), null, \core\output\notification::NOTIFY_WARNING);
}

// Create new url.
$addnewurl = new moodle_url('/blocks/opencast/addtranscription.php',
    array('video_identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

// Check if delete option is allowed.
$candelete = false;
if (!empty(get_config('block_opencast', 'deletetranscriptionworkflow_' . $ocinstanceid))) {
    $candelete = true;
}

// Preparing flavors as for service types.
$flavorsconfig = get_config('block_opencast', 'transcriptionflavors_' . $ocinstanceid);
$flavors = [];
if (!empty($flavorsconfig)) {
    $flavorsarray = json_decode($flavorsconfig);
    foreach ($flavorsarray as $flavor) {
        if (!empty($flavor->key) && !empty($flavor->value)) {
            $flavors[$flavor->key] = format_string($flavor->value);
        }
    }
}
// Check if download button should perform LTI, then we pass _blank as its target.
$downloadblanktarget = get_config('block_opencast', 'ltidownloadtranscription_' . $ocinstanceid);
// Extract caption from attachments.
$list = [];
$mediapackagexml = $apibridge->get_event_media_package($identifier);
$mediapackage = simplexml_load_string($mediapackagexml);
foreach ($mediapackage->attachments->attachment as $attachment) {
    $attachmentarray = json_decode(json_encode((array) $attachment));
    $type = $attachmentarray->{'@attributes'}->type;
    if (strpos($type, 'captions/vtt') !== false) {
        // Extracting language to be displayed in the table.
        $flavortype = str_replace('captions/vtt+', '', $type);
        $flavorname = '';
        if (array_key_exists($flavortype, $flavors)) {
            $flavorname = $flavors[$flavortype];
        }
        $attachmentarray->flavor = !empty($flavorname) ?
            $flavorname :
            get_string('notranscriptionflavor', 'block_opencast', $flavortype);

        // Extracting id and type from attributes.
        $attachmentarray->id = $attachmentarray->{'@attributes'}->id;
        $attachmentarray->type = $type;

        // Preparing delete url.
        $deleteurl = new moodle_url('/blocks/opencast/deletetranscription.php',
        array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
            'video_identifier' => $identifier, 'transcription_identifier' => $attachmentarray->id));
        $attachmentarray->deleteurl = $deleteurl->out(false);

        // Preparing download url.
        $downloadurl = new moodle_url('/blocks/opencast/downloadtranscription.php',
        array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
            'video_identifier' => $identifier, 'attachment_type' => str_replace(['/', '+'], ['-', '_'], $type)));
        $attachmentarray->downloadurl = $downloadurl->out(false);

        $list[] = $attachmentarray;
    }
}
/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetranscriptions_header', 'block_opencast'));
echo $renderer->render_manage_transcriptions_table($list, $addnewurl->out(false), $candelete, $downloadblanktarget);
echo $OUTPUT->footer();
