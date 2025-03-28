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
 * Redirects users to the engage player and authenticates them using LTI.
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel, WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\apibridge;
use block_opencast\local\lti_helper;
use tool_opencast\local\settings_api;

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once($CFG->dirroot . '/lib/oauthlib.php');

global $PAGE, $OUTPUT, $CFG, $USER;

// Get all the params from the get call.
$identifier = required_param('identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

// Preparing the urls for the page.
$redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$baseurl = new moodle_url('/blocks/opencast/engageredirect.php',
    ['identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$PAGE->set_url($baseurl);

// Check if the user is logged in.
require_login($courseid, false);

$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('engageredirect', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

$endpoint = get_config('tool_opencast', 'engageurl_' . $ocinstanceid);

// Make sure the endpoint is correct.
if (strpos($endpoint, 'http') !== 0) {
    $endpoint = 'http://' . $endpoint;
}

$url = $endpoint . '/play/' . $identifier;

$opencast = apibridge::get_instance($ocinstanceid);

$consumerkey = $opencast->get_lti_consumerkey();
$consumersecret = $opencast->get_lti_consumersecret();

if (empty($consumerkey)) {
    redirect($url);
}

$ltiendpoint = rtrim($endpoint, '/') . '/lti';

// Create parameters.
$params = lti_helper::create_lti_parameters($consumerkey, $consumersecret, $ltiendpoint, $url);

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('engageredirect', 'block_opencast'));
echo $renderer->render_lti_form($ltiendpoint, $params);

$PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init');
echo $OUTPUT->footer();
