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

global $PAGE, $OUTPUT, $CFG, $USER, $SITE;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/recordvideo.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

// Capability check.
$context = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $context);

$PAGE->set_context($context);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('recordvideo', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

$endpoint = \tool_opencast\local\settings_api::get_apiurl($ocinstanceid);

if (!empty(get_config('block_opencast', 'opencast_studio_baseurl_' . $ocinstanceid))) {
    $endpoint = get_config('block_opencast', 'opencast_studio_baseurl_' . $ocinstanceid);
}

if (strpos($endpoint, 'http') !== 0) {
    $endpoint = 'http://' . $endpoint;
}

$ltiendpoint = rtrim($endpoint, '/') . '/lti';

$apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

// Get series ID, create a new one if necessary.
$seriesid = $apibridge->get_stored_seriesid($courseid, true, $USER->id);

// Get Studio url path to insert as customtool.
$customtool = $apibridge->generate_studio_url_path($courseid, $seriesid);

// Create parameters.
$consumerkey = $apibridge->get_lti_consumerkey();
$consumersecret = $apibridge->get_lti_consumersecret();
$params = \block_opencast\local\lti_helper::create_lti_parameters($consumerkey, $consumersecret, $ltiendpoint, $customtool);

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recordvideo', 'block_opencast'));
echo $renderer->render_lti_form($ltiendpoint, $params);

$PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init');
echo $OUTPUT->footer();
