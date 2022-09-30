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

$api = \block_opencast\local\apibridge::get_instance($ocinstanceid);

// Get series ID, create a new one if necessary.
$seriesid = $api->get_stored_seriesid($courseid, true, $USER->id);

// Create lti customtool to redirect to Studio.
$customtoolparams = [];
// Check if Studio return button is enabled.
if (get_config('block_opencast', 'show_opencast_studio_return_btn_' . $ocinstanceid)) {
    // Initializing default label for studio return button.
    $studioreturnbtnlabel = $SITE->fullname;
    // Check if custom label is configured.
    if (!empty(get_config('block_opencast', 'opencast_studio_return_btn_label_' . $ocinstanceid))) {
        $studioreturnbtnlabel = get_config('block_opencast', 'opencast_studio_return_btn_label_' . $ocinstanceid);
    }

    // Initializing default studio return url.
    $studioreturnurl = new moodle_url('/blocks/opencast/index.php',
        array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
    // Check if custom return url is configured.
    if (!empty(get_config('block_opencast', 'opencast_studio_return_url_' . $ocinstanceid))) {
        // Prepare the custom url.
        $customreturnurl = get_config('block_opencast', 'opencast_studio_return_url_' . $ocinstanceid);
        // Slipt it into parts, to extract endpoint and query strings.
        $customreturnurlarray = explode('?', $customreturnurl);
        $customurl = $customreturnurlarray[0];
        $customquerystring = count($customreturnurlarray) > 1 ? $customreturnurlarray[1] : null;

        $customurldata = [];
        // If there is any query string.
        if (!empty($customquerystring)) {
            // Split them.
            $customquerystringdata = explode('&', $customquerystring);
            // Put them into loop to replace the placeholders and add them into the customurldata array.
            foreach ($customquerystringdata as $data) {
                $datastring = str_replace(['[COURSEID]', '[OCINSTANCEID]'], [$courseid, $ocinstanceid], $data);
                $dataarray = explode('=', $datastring);
                if (count($dataarray) == 2) {
                    $customurldata[$dataarray[0]] = $dataarray[1];
                }
            }
        }

        if (!empty($customurl)) {
            $studioreturnurl = new moodle_url($customurl, $customurldata);
        }
    }

    // Appending studio return data, only when there is a url.
    if (!empty($studioreturnurl)) {
        $customtoolparams[] = 'return.label=' . urlencode($studioreturnbtnlabel);
        $customtoolparams[] = 'return.target=' . urlencode($studioreturnurl->out(false));
    }
}
$customtoolparams[] = 'upload.seriesId=' . $seriesid;
$customtool = '/studio?' . implode('&', $customtoolparams);
// Create parameters.

$consumerkey = $api->get_lti_consumerkey();
$consumersecret = $api->get_lti_consumersecret();
$params = \block_opencast\local\lti_helper::create_lti_parameters($consumerkey, $consumersecret, $ltiendpoint, $customtool);

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recordvideo', 'block_opencast'));
echo $renderer->render_lti_form($ltiendpoint, $params);

$PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init');
echo $OUTPUT->footer();
