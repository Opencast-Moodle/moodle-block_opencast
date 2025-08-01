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
 * Delete event's transcriptions
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\attachment_helper;
use block_opencast\local\apibridge;
use core\output\notification;
use tool_opencast\local\settings_api;
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $SITE;

require_once($CFG->dirroot . '/repository/lib.php');

$videoidentifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$identifier = required_param('transcription_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$indexurl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$redirecturl = new moodle_url('/blocks/opencast/managetranscriptions.php',
    ['video_identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$baseurl = new moodle_url('/blocks/opencast/deletetranscription.php',
    ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
        'video_identifier' => $videoidentifier, 'transcription_identifier' => $identifier, ]);
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $indexurl);
$PAGE->navbar->add(get_string('managetranscriptions', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('deletetranscription', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$video = $apibridge->get_opencast_video($videoidentifier, true);

$transcriptionmanagementenabled = (bool) get_config('block_opencast', 'enablemanagetranscription_' . $ocinstanceid);
if (!$transcriptionmanagementenabled) {
    redirect($redirecturl,
        get_string('transcriptionmanagementdisabled', 'block_opencast'), null, notification::NOTIFY_ERROR);
}

if ($video->error || $video->video->processing_state != 'SUCCEEDED') {
    redirect($redirecturl,
        get_string('unabletodeletetranscription', 'block_opencast'), null, notification::NOTIFY_ERROR);
}

if (($action == 'delete') && confirm_sesskey()) {
    // In order to remove the transcription from the media package we need to look it up in publication first,
    // then we find it in the media package based on flavor and tags, because we maintain the compatibility of both ways.

    $transcriptiontodelete = null;
    foreach ($video->video->publications as $publication) {
        // Search the media.
        foreach ($publication->media as $media) {
            if ($media->id == $identifier) {
                $transcriptiontodelete = $media;
                break 2;
            }
        }
    }

    $deleted = false;
    if (!empty($transcriptiontodelete)) {
        $deleted = attachment_helper::delete_transcription($ocinstanceid, $videoidentifier, $transcriptiontodelete);
    }

    $message = get_string('transcriptiondeletionsucceeded', 'block_opencast');
    $status = notification::NOTIFY_SUCCESS;
    if (!$deleted) {
        $message = get_string('transcriptiondeletionfailed', 'block_opencast');
        $status = notification::NOTIFY_ERROR;
    }
    redirect($redirecturl, $message, null, $status);
}

$label = get_string('deletetranscription_desc', 'block_opencast');
$params = [
    'transcription_identifier' => $identifier,
    'courseid' => $courseid,
    'action' => 'delete',
    'ocinstanceid' => $ocinstanceid,
    'video_identifier' => $videoidentifier,
];
$urldelete = new moodle_url('/blocks/opencast/deletetranscription.php', $params);
$html = $OUTPUT->confirm($label, $urldelete, $redirecturl);

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deletetranscription', 'block_opencast'));
echo $html;
echo $OUTPUT->footer();
