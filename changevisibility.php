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
 * @copyright  2018 Tobias Reischmann, WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('./renderer.php');

use block_opencast\local\apibridge;

global $PAGE, $OUTPUT, $CFG;

$identifier = required_param('identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id,PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/changevisibility.php', array('identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('changevisibility', 'block_opencast'), $baseurl);

// Check if the ACL control feature is enabled.
if (get_config('block_opencast', 'aclcontrolafter_'. $ocinstanceid) != true) {
    throw new moodle_exception('ACL control feature not enabled', 'block_opencast', $redirecturl);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$visibility = $apibridge->is_event_visible($identifier, $courseid);
if ($visibility === \block_opencast_renderer::MIXED_VISIBLITY) {
    $groups = \block_opencast\groupaccess::get_record(array('opencasteventid' => $identifier, 'ocinstanceid' => $ocinstanceid));
    if ($groups) {
        $visibility = \block_opencast_renderer::GROUP;
    } else {
        $visibility = \block_opencast_renderer::HIDDEN;
    }
}

$changevisibilityform = new \block_opencast\local\visibility_form(null, array('courseid' => $courseid,
    'identifier' => $identifier, 'visibility' => $visibility, 'ocinstanceid' => $ocinstanceid));

// Check if video exists.
$courseseries = $apibridge->get_course_series($courseid);
$video = null;
foreach($courseseries as $series) {
    $videos = $apibridge->get_series_videos($series->series);
    foreach ($videos->videos as $v) {
        if ($v->identifier === $identifier) {
            $video = $v;
            break;
        }
    }
    if($video) {
        break;
    }
}

if (!$video) {
    $message = get_string('videonotfound', 'block_opencast');
    redirect($redirecturl, $message);
}

// Workflow is not set.
if (get_config('block_opencast', 'workflow_roles_' . $ocinstanceid) == "") {
    $message = get_string('workflownotdefined', 'block_opencast', $video->video);
    redirect($redirecturl, $message, null, \core\notification::ERROR);
}


if ($changevisibilityform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $changevisibilityform->get_data()) {
    if (confirm_sesskey()) {

        $groups = null;
        if (property_exists($data, 'groups')) {
            $groups = $data->groups;
        }
        // Alter group access.
        if ($code = $apibridge->change_visibility($identifier, $courseid, $data->visibility, $groups)) {
            redirect($redirecturl, get_string($code, 'block_opencast', $video), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($redirecturl, get_string('aclroleschangeerror', 'block_opencast', $video),
                null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('changevisibility_header', 'block_opencast', $video));
$changevisibilityform->display();
echo $OUTPUT->footer();
