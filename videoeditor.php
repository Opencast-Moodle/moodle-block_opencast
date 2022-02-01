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
 * Redirects users to Opencast Studio for recording videos.
 * @package    block_opencast
 * @copyright  2020 Farbod Zamani Boroujeni - ELAN e.V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once($CFG->dirroot . '/lib/oauthlib.php');

use block_opencast\local\upload_helper;

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

if (empty(get_config('block_opencast', 'editorlticonsumerkey_' . $ocinstanceid))) {
    redirect($editorbaseurl . $editorendpoint);
}

$ltiendpoint = rtrim($editorbaseurl, '/') . '/lti';

$opencast = \block_opencast\local\apibridge::get_instance($ocinstanceid);
$video = $opencast->get_opencast_video($identifier);

// Validate the video and make sure the video can be edited by the editor (double check).
if ((empty($editorbaseurl) || empty($editorendpoint) ||
    !$video || $video->error == true || !$opencast->can_edit_event_in_editor($video->video, $courseid))) {
    redirect($redirecturl, get_string('videoeditorinvalidconfig', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
}

// Create parameters.
$params = block_opencast_create_editor_lti_parameters($ocinstanceid, $ltiendpoint, $editorendpoint);

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('videoeditor_short', 'block_opencast'));
echo $renderer->render_lti_form($ltiendpoint, $params);

$PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init');
echo $OUTPUT->footer();

/**
 * Create necessary editor lti parameters.
 *
 * @param int $ocinstanceid Opencast instance id.
 * @param string $endpoint of the opencast instance.
 * @param string $customtool the custom tool to used in order to redirect after lti authentication.
 *
 * @return array lti parameters
 * @throws dml_exception
 * @throws moodle_exception
 */
function block_opencast_create_editor_lti_parameters($ocinstanceid, $endpoint, $customtool) {
    global $CFG, $COURSE, $USER;

    // Get consumerkey and consumersecret.
    $consumerkey = get_config('block_opencast', 'editorlticonsumerkey_' . $ocinstanceid);
    $consumersecret = get_config('block_opencast', 'editorlticonsumersecret_' . $ocinstanceid);

    $helper = new oauth_helper(array('oauth_consumer_key' => $consumerkey,
        'oauth_consumer_secret' => $consumersecret));

    // Set all necessary parameters.
    $params = array();
    $params['oauth_version'] = '1.0';
    $params['oauth_nonce'] = $helper->get_nonce();
    $params['oauth_timestamp'] = $helper->get_timestamp();
    $params['oauth_consumer_key'] = $consumerkey;

    $params['context_id'] = $COURSE->id;
    $params['context_label'] = trim($COURSE->shortname);
    $params['context_title'] = trim($COURSE->fullname);
    $params['resource_link_id'] = 'o' . random_int(1000, 9999) . '-' . random_int(1000, 9999);
    $params['resource_link_title'] = 'Opencast';
    $params['context_type'] = ($COURSE->format == 'site') ? 'Group' : 'CourseSection';
    $params['launch_presentation_locale'] = current_language();
    $params['ext_lms'] = 'moodle-2';
    $params['tool_consumer_info_product_family_code'] = 'moodle';
    $params['tool_consumer_info_version'] = strval($CFG->version);
    $params['oauth_callback'] = 'about:blank';
    $params['lti_version'] = 'LTI-1p0';
    $params['lti_message_type'] = 'basic-lti-launch-request';
    $urlparts = parse_url($CFG->wwwroot);
    $params['tool_consumer_instance_guid'] = $urlparts['host'];
    $params['custom_tool'] = $customtool;

    // User data.
    $params['user_id'] = $USER->id;
    $params['lis_person_name_given'] = $USER->firstname;
    $params['lis_person_name_family'] = $USER->lastname;
    $params['lis_person_name_full'] = $USER->firstname . ' ' . $USER->lastname;
    $params['ext_user_username'] = $USER->username;
    $params['lis_person_contact_email_primary'] = $USER->email;
    $params['roles'] = lti_get_ims_role($USER, null, $COURSE->id, false);

    if (!empty($CFG->mod_lti_institution_name)) {
        $params['tool_consumer_instance_name'] = trim(html_to_text($CFG->mod_lti_institution_name, 0));
    } else {
        $params['tool_consumer_instance_name'] = get_site()->shortname;
    }

    $params['launch_presentation_document_target'] = 'iframe';
    $params['oauth_signature_method'] = 'HMAC-SHA1';
    $signedparams = lti_sign_parameters($params, $endpoint, "POST", $consumerkey, $consumersecret);
    $params['oauth_signature'] = $signedparams['oauth_signature'];

    return $params;
}
