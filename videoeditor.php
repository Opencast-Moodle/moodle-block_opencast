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
 * Redirects users to Opencast Editor.
 * @package    block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni - ELAN e.V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once($CFG->dirroot . '/lib/oauthlib.php');

global $PAGE, $OUTPUT, $CFG, $USER;

require_once($CFG->dirroot . '/repository/lib.php');

$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$baseurl = new moodle_url('/blocks/opencast/videoeditor.php',
    array('video_identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

// Capability check.
$context = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $context);

$PAGE->set_context($context);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('videoeditor_short', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

$endpoint = \tool_opencast\local\settings_api::get_apiurl($ocinstanceid);

$editorendpoint = get_config('block_opencast', 'editorendpointurl_' . $ocinstanceid);
if (empty($editorendpoint)) {
    $editorendpoint = '/editor-ui/index.html?mediaPackageId=';
}

$editorendpoint = '/' . ltrim($editorendpoint, '/') . $identifier;

$editorbaseurl = get_config('block_opencast', 'editorbaseurl_' . $ocinstanceid);
if (empty($editorbaseurl)) {
    $editorbaseurl = $endpoint;
}

if (strpos($editorbaseurl, 'http') !== 0) {
    $editorbaseurl = 'http://' . $editorbaseurl;
}

$opencast = \block_opencast\local\apibridge::get_instance($ocinstanceid);

$consumerkey = $opencast->get_lti_consumerkey();
$consumersecret = $opencast->get_lti_consumersecret();

if (empty($consumerkey)) {
    redirect($editorbaseurl . $editorendpoint);
}

$ltiendpoint = rtrim($editorbaseurl, '/') . '/lti';

$video = $opencast->get_opencast_video($identifier);

// Validate the video and make sure the video can be edited by the editor (double check).
if ((empty($editorbaseurl) || empty($editorendpoint) ||
    !$video || $video->error == true || !$opencast->can_edit_event_in_editor($video->video, $courseid))) {
    redirect($redirecturl, get_string('videoeditorinvalidconfig', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
}

// Create parameters.
$params = \block_opencast\local\lti_helper::create_lti_parameters($consumerkey, $consumersecret, $ltiendpoint, $editorendpoint);

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('videoeditor_short', 'block_opencast'));
echo $renderer->render_lti_form($ltiendpoint, $params);

$PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init');
echo $OUTPUT->footer();
