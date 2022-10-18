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

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$type = required_param('attachment_type', PARAM_ALPHANUMEXT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$indexurl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$baseurl = new moodle_url('/blocks/opencast/downloadtranscription.php',
    array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
        'video_identifier' => $identifier, 'attachement_identifier' => $attachementid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/managetranscriptions.php',
    array('video_identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

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

$apibridge = apibridge::get_instance($ocinstanceid);
$result = $apibridge->get_opencast_video($identifier, true);
if (!$result->error || $result->video->processing_state != 'SUCCEEDED' ||
    empty(get_config('block_opencast', 'transcriptionworkflow_' . $ocinstanceid))) {
    $video = $result->video;
    $downloadurl = '';
    $type = str_replace(['-', '_'], ['/', '+'], $type);
    foreach ($video->publications as $publication) {
        foreach ($publication->attachments as $attachment) {
            if ($attachment->flavor == $type) {
                $downloadurl = $attachment->url;
                break 2;
            }
        }
    }

    if (empty($downloadurl)) {
        redirect($redirecturl,
            get_string('unabletodownloadtranscription', 'block_opencast'),
            null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $endpoint = \tool_opencast\local\settings_api::get_apiurl($ocinstanceid);

    // Make sure the endpoint is correct.
    if (strpos($endpoint, 'http') !== 0) {
        $endpoint = 'http://' . $endpoint;
    }

    $consumerkey = $apibridge->get_lti_consumerkey();
    $consumersecret = $apibridge->get_lti_consumersecret();

    if (empty($consumerkey)) {
        redirect($downloadurl);
    }

    $ltiendpoint = rtrim($endpoint, '/') . '/lti';

    // Create parameters.
    $params = \block_opencast\local\lti_helper::create_lti_parameters($consumerkey, $consumersecret, $ltiendpoint, $downloadurl);

    $renderer = $PAGE->get_renderer('block_opencast');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('downloadtranscription', 'block_opencast'));
    echo $renderer->render_lti_form($ltiendpoint, $params);

    $PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init');
    echo $OUTPUT->footer();
} else {
    redirect($redirecturl,
        get_string('unabletodownloadtranscription', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}
