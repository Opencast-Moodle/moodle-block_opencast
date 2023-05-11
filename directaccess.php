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
 * Direct access to the given video.
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
$videoid = required_param('video_identifier', PARAM_ALPHANUMEXT);
$mediaid = required_param('mediaid', PARAM_ALPHANUMEXT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/directaccess.php',
    array('courseid' => $courseid, 'video_identifier' => $videoid,
        'mediaid' => $mediaid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('directaccesstovideo', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('directaccesstovideo', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
try {
    require_capability('block/opencast:directaccessvideolink', $coursecontext);
} catch (\required_capability_exception $e) {
    // We gently redirect to the course main view page in case of capability exception, to handle the behat more sufficiently.
    $redirecttocourse = new moodle_url('/course/view.php', array('id' => $courseid));
    redirect($redirecttocourse,
        get_string('nopermissions', 'error', get_string('opencast:directaccessvideolink', 'block_opencast')),
        null,
        \core\output\notification::NOTIFY_ERROR);
}

$apibridge = apibridge::get_instance($ocinstanceid);
$result = $apibridge->get_opencast_video($videoid, true);
if (!$result->error) {
    $video = $result->video;
    if ($video->is_accessible) {
        $directaccessurl = '';
        foreach ($video->publications as $publication) {
            if ($publication->channel == get_config('block_opencast', 'direct_access_channel_' . $ocinstanceid)) {
                foreach ($publication->media as $media) {
                    if ($media->id === $mediaid) {
                        $directaccessurl = $media->url;
                        break 2;
                    }
                }
            }
        }

        if (empty($directaccessurl)) {
            redirect($redirecturl,
                get_string('video_not_accessible', 'block_opencast'),
                null,
                \core\output\notification::NOTIFY_ERROR);
        }

        $endpoint = \tool_opencast\local\settings_api::get_apiurl($ocinstanceid);

        // Make sure the endpoint is correct.
        if (strpos($endpoint, 'http') !== 0) {
            $endpoint = 'http://' . $endpoint;
        }

        $apibridge = apibridge::get_instance($ocinstanceid);

        $consumerkey = $apibridge->get_lti_consumerkey();
        $consumersecret = $apibridge->get_lti_consumersecret();

        if (empty($consumerkey)) {
            redirect($directaccessurl);
        }

        $ltiendpoint = rtrim($endpoint, '/') . '/lti';

        // Create parameters.
        $params = \block_opencast\local\lti_helper::create_lti_parameters($consumerkey, $consumersecret,
            $ltiendpoint, $directaccessurl);

        $renderer = $PAGE->get_renderer('block_opencast');

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('directaccesstovideo', 'block_opencast'));
        echo $renderer->render_lti_form($ltiendpoint, $params);

        $waitingtime = 0;
        if (defined('BEHAT_SITE_RUNNING')) {
            $waitingtime = 2;
        }

        $PAGE->requires->js_call_amd('block_opencast/block_lti_form_handler', 'init', [$waitingtime]);
        echo $OUTPUT->footer();

    } else {
        redirect($redirecturl,
            get_string('video_not_accessible', 'block_opencast'),
            null,
            \core\output\notification::NOTIFY_ERROR);
    }
} else {
    redirect($redirecturl,
        get_string('video_retrieval_failed', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}
