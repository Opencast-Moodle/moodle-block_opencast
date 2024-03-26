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
 * Download Transcription file with lti auth.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once($CFG->dirroot . '/lib/oauthlib.php');

use block_opencast\local\apibridge;
use block_opencast\local\lti_helper;
use core\output\notification;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$transcriptionid = required_param('transcription_identifier', PARAM_ALPHANUMEXT);
$domain = optional_param('domain', '', PARAM_ALPHA);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$indexurl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$baseurl = new moodle_url('/blocks/opencast/downloadtranscription.php',
    ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
        'video_identifier' => $identifier, 'transcription_identifier' => $transcriptionid,]);
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/managetranscriptions.php',
    ['video_identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('downloadtranscription', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $indexurl);
$PAGE->navbar->add(get_string('managetranscriptions', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('downloadtranscription', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

// Make sure transcription as well as the downlaod is enabled.
$transcriptionenabled = get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid);
$downloadenabled = get_config('block_opencast', 'allowdownloadtranscription_' . $ocinstanceid);
if (empty($downloadenabled) || empty($transcriptionenabled)) {
    redirect($redirecturl,
        get_string('unabletodownloadtranscription', 'block_opencast'),
        null,
        notification::NOTIFY_ERROR);
}

$apibridge = apibridge::get_instance($ocinstanceid);
$result = $apibridge->get_opencast_video($identifier, true, false, true);
// Make sure video is in good condition.
if ($result->error || $result->video->processing_state != 'SUCCEEDED') {
    redirect($redirecturl,
        get_string('unabletodownloadtranscription', 'block_opencast'),
        null,
        notification::NOTIFY_ERROR);
}

$downloadurl = '';
$size = 0;
$mimetype = 'text/vtt'; // It should be vtt.
// Going through publications.
$publicationmedia = null;
foreach ($result->video->publications as $publication) {
    // Search the attachments.
    foreach ($publication->attachments as $attachment) {
        if ($attachment->id == $transcriptionid) {
            $downloadurl = $attachment->url;
            $size = $attachment->size;
            $mimetype = $attachment->mediatype;
            break 2;
        }
    }
    // Search the media.
    foreach ($publication->media as $media) {
        if ($media->id == $transcriptionid) {
            // We record the media object for a further lookup in media tracks.
            $publicationmedia = $media;
            $downloadurl = $media->url;
            $size = $media->size;
            $mimetype = $media->mediatype;
            break 2;
        }
    }
}

// Here in order to have the right downloadable url for media, we need to find it from video media data.
// This case happens when using LTI and redirecting to assets/assets, otherwise it displays the file.
if ($domain === 'media' && !empty($result->video->media) && !empty($publicationmedia)) {
    foreach ($result->video->media as $track) {
        if ($track->mimetype == $publicationmedia->mediatype &&
            $track->flavor == $publicationmedia->flavor &&
            $track->tags == $publicationmedia->tags) {
            $downloadurl = $track->uri;
            $size = $track->size;
            $mimetype = $track->mimetype;
            break;
        }
    }
}

if (empty($downloadurl)) {
    redirect($redirecturl,
        get_string('unabletodownloadtranscription', 'block_opencast'),
        null,
        notification::NOTIFY_ERROR);
}

// Get the LTI required credentials.
$consumerkey = $apibridge->get_lti_consumerkey();
$consumersecret = $apibridge->get_lti_consumersecret();

// We set a flag to determine whether we should perform LTI authentication.
$performlti = true;
// If no key is provided, we proceed with no LTI authentication.
if (empty($consumerkey)) {
    $performlti = false;
}

if ($performlti) {
    $endpoint = settings_api::get_apiurl($ocinstanceid);

    // Make sure the endpoint is correct.
    if (strpos($endpoint, 'http') !== 0) {
        $endpoint = 'http://' . $endpoint;
    }

    $ltiendpoint = rtrim($endpoint, '/') . '/lti';

    // Create parameters.
    $params = lti_helper::create_lti_parameters($consumerkey, $consumersecret,
        $ltiendpoint, $downloadurl);

    $renderer = $PAGE->get_renderer('block_opencast');
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('downloadtranscription', 'block_opencast'));
    echo $renderer->render_lti_form($ltiendpoint, $params);
    $PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init');
    $htmlreturnlink = html_writer::link($redirecturl, get_string('transcriptionreturntomanagement', 'block_opencast'));
    echo html_writer::tag('p', get_string('transcriptionltidownloadcompleted', 'block_opencast', $htmlreturnlink));
    echo $OUTPUT->footer();
} else {
    ob_clean();
    $urlparts = explode('/', $downloadurl);
    $filename = $urlparts[count($urlparts) - 1];

    header('Content-Description: Download Transcription File');
    header('Content-Type: ' . $mimetype);
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Length: ' . $size);

    if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
        header('Cache-Control: private, max-age=10, no-transform');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: ');
    } else { // Normal http - prevent caching at all cost.
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0, no-transform');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: no-cache');
    }

    readfile($downloadurl);
}
