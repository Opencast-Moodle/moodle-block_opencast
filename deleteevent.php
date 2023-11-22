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
 * Page overview.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\apibridge;
use block_opencast\local\upload_helper;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $SITE;

$identifier = required_param('identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);
$redirectpage = optional_param('redirectpage', null, PARAM_ALPHA);
$series = optional_param('series', null, PARAM_ALPHANUMEXT);

$baseurl = new moodle_url('/blocks/opencast/deleteevent.php',
    array('identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
        'redirectpage' => $redirectpage, 'series' => $series));
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

if ($redirectpage == 'overviewvideos') {
    $redirecturl = new moodle_url('/blocks/opencast/overview_videos.php', array('ocinstanceid' => $ocinstanceid,
        'series' => $series));
} else if ($redirectpage == 'overview') {
    $redirecturl = new moodle_url('/blocks/opencast/overview.php', array('ocinstanceid' => $ocinstanceid));
} else {
    $redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
}

$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('deleteevent', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:deleteevent', $coursecontext);

$opencast = apibridge::get_instance($ocinstanceid);
$video = $opencast->get_opencast_video($identifier);

if (($action == 'delete') && confirm_sesskey()) {
    // Do action.
    if ($video->video) {
        if ($opencast->trigger_delete_event($video->video->identifier)) {
            $message = get_string('eventdeletionstarted', 'block_opencast', $video->video);
            redirect($redirecturl, $message);
        } else {
            $message = get_string('eventdeletedfailed', 'block_opencast', $video->video);
            redirect($redirecturl, $message);
        }
    }

    $message = get_string('videonotfound', 'block_opencast');
    redirect($redirecturl, $message);
}

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deleteevent', 'block_opencast'));
echo $renderer->render_video_deletion_info($ocinstanceid, $courseid, $video->video, $redirectpage, $series);
echo $OUTPUT->footer();
