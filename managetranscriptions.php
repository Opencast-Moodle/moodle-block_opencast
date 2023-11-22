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
use core\output\notification;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $SITE;

require_once($CFG->dirroot . '/repository/lib.php');

$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

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
        get_string('unabletomanagetranscriptions', 'block_opencast'), null, notification::NOTIFY_WARNING);
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
/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

// Check if download is enabled.
$allowdownload = get_config('block_opencast', 'allowdownloadtranscription_' . $ocinstanceid);

// Extract caption from attachments/media.
$list = [];
$mediapackagestring = $apibridge->get_event_media_package($identifier);
$mediapackagexml = simplexml_load_string($mediapackagestring);

// First try to get attachments.
$attachmentitems = [];
if (property_exists($mediapackagexml, 'attachments')) {
    $attachments = [];
    foreach ($mediapackagexml->attachments->attachment as $attachment) {
        $attachments[] = $attachment;
    }
    $attachmentitems = $renderer->prepare_transcription_items_for_the_menu($attachments, $courseid, $ocinstanceid, $identifier,
        'attachments', $flavors);
}

// Then try to get media.
$mediaitems = [];
if (property_exists($mediapackagexml, 'media')) {
    $mediatracks = [];
    foreach ($mediapackagexml->media->track as $track) {
        $mediatracks[] = $track;
    }
    $mediaitems = $renderer->prepare_transcription_items_for_the_menu($mediatracks, $courseid, $ocinstanceid, $identifier,
        'media', $flavors);
}

// After that, we merge everything together.
$list = array_merge($mediaitems, $attachmentitems);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetranscriptions_header', 'block_opencast'));
echo $renderer->render_manage_transcriptions_table($list, $addnewurl->out(false), $candelete, $allowdownload);
echo $OUTPUT->footer();
