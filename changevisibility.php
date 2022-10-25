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
use block_opencast\local\visibility_helper;

global $PAGE, $OUTPUT, $CFG;

$identifier = required_param('identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/changevisibility.php',
    array('identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('changevisibility', 'block_opencast'), $baseurl);

// Check if the ACL control feature is enabled.
if (get_config('block_opencast', 'aclcontrolafter_' . $ocinstanceid) != true) {
    throw new moodle_exception('ACL control feature not enabled', 'block_opencast', $redirecturl);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$visibility = $apibridge->is_event_visible($identifier, $courseid);
if ($visibility === \block_opencast_renderer::MIXED_VISIBILITY) {
    $groups = \block_opencast\groupaccess::get_record(array('opencasteventid' => $identifier, 'ocinstanceid' => $ocinstanceid));
    if ($groups) {
        $visibility = \block_opencast_renderer::GROUP;
    } else {
        $visibility = \block_opencast_renderer::HIDDEN;
    }
}
$scheduledvisibility = visibility_helper::get_event_scheduled_visibility($ocinstanceid, $courseid, $identifier);

$changevisibilityform = new \block_opencast\local\visibility_form(null, array('courseid' => $courseid,
    'identifier' => $identifier, 'visibility' => $visibility, 'ocinstanceid' => $ocinstanceid,
    'scheduledvisibility' => $scheduledvisibility));

// Check if video exists.
$courseseries = $apibridge->get_course_series($courseid);
$video = null;
foreach ($courseseries as $series) {
    $videos = $apibridge->get_series_videos($series->series);
    foreach ($videos->videos as $v) {
        if ($v->identifier === $identifier) {
            $video = $v;
            break;
        }
    }
    if ($video) {
        break;
    }
}

if (!$video) {
    $message = get_string('videonotfound', 'block_opencast');
    redirect($redirecturl, $message);
}

if ($video->processing_state == 'RUNNING' || $video->processing_state == 'PAUSED') {
    $message = get_string('worklowisrunning', 'block_opencast');
    redirect($redirecturl, $message, null, \core\output\notification::NOTIFY_WARNING);
}

// Workflow is not set.
if (get_config('block_opencast', 'workflow_roles_' . $ocinstanceid) == "") {
    $message = get_string('workflownotdefined', 'block_opencast');
    redirect($redirecturl, $message, null, \core\notification::ERROR);
}

if ($changevisibilityform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $changevisibilityform->get_data()) {
    if (confirm_sesskey()) {

        $visibilitycode = '';
        $requestscheduling = false;
        if (isset($data->enableschedulingchangevisibility) && boolval($data->enableschedulingchangevisibility)) {
            $requestscheduling = true;
        }
        // Change current visibility, if it is different from the previous visibility status.
        if ($visibility != $data->visibility) {
            $groups = null;
            if (property_exists($data, 'groups')) {
                $groups = $data->groups;
            }
            $visibilitycode = $apibridge->change_visibility($identifier, $courseid, $data->visibility, $groups);
            // If there is any error, redirect with error message and skip the scheduling process.
            if ($visibilitycode === false) {
                $text = get_string('aclroleschangeerror', 'block_opencast', $video);
                if ($requestscheduling) {
                    $text .= get_string('scheduledvisibilitychangeskipped', 'block_opencast');
                }
                redirect($redirecturl, $text, null, \core\output\notification::NOTIFY_ERROR);
            }
        }

        $schedulingcode = '';
        $schedulingresult = true;
        // Check if the scheduled visibility is set, we update the record.
        if ($requestscheduling) {
            $initialvisibilitygroups = null;
            if ($data->visibility == \block_opencast_renderer::GROUP
                && !empty($data->groups)) {
                $initialvisibilitygroups = json_encode($data->groups);
            }
            $scheduledvisibilitygroups = null;
            if ($data->scheduledvisibilitystatus == \block_opencast_renderer::GROUP
                && !empty($data->scheduledvisibilitygroups)) {
                $scheduledvisibilitygroups = json_encode($data->scheduledvisibilitygroups);
            }

            // If the record already exists, we update it.
            if (!empty($scheduledvisibility)) {
                $scheduledvisibility->scheduledvisibilitytime = $data->scheduledvisibilitytime;
                $scheduledvisibility->scheduledvisibilitystatus = $data->scheduledvisibilitystatus;
                $scheduledvisibility->scheduledvisibilitygroups = $scheduledvisibilitygroups;
                $schedulingresult = visibility_helper::update_visibility_job($scheduledvisibility);
                $schedulingcode = $schedulingresult ? 'scheduledvisibilitychangeupdated' : 'scheduledvisibilityupdatefailed';
            } else {
                // Otherwise, we create a new record.
                $scheduledvisibility = new \stdClass();
                $scheduledvisibility->initialvisibilitystatus = $data->visibility;
                $scheduledvisibility->initialvisibilitygroups = $initialvisibilitygroups;
                $scheduledvisibility->scheduledvisibilitytime = $data->scheduledvisibilitytime;
                $scheduledvisibility->scheduledvisibilitystatus = $data->scheduledvisibilitystatus;
                $scheduledvisibility->scheduledvisibilitygroups = $scheduledvisibilitygroups;
                $scheduledvisibility->ocinstanceid = $ocinstanceid;
                $scheduledvisibility->courseid = $courseid;
                $scheduledvisibility->opencasteventid = $identifier;
                $schedulingresult = visibility_helper::save_visibility_job($scheduledvisibility);
                $schedulingcode = $schedulingresult ? 'scheduledvisibilitychangecreated' : 'scheduledvisibilitycreatefailed';
            }
        } else if (!empty($scheduledvisibility)) {
            // If disabled and there is a scheduled visibility record, that means that it has to be removed.
            $schedulingresult = visibility_helper::delete_visibility_job($scheduledvisibility);
            $schedulingcode = $schedulingresult ? 'scheduledvisibilitychangedeleted' : 'scheduledvisibilitydeletefailed';
        }

        $text = '';
        $status = \core\output\notification::NOTIFY_SUCCESS;
        if (!empty($visibilitycode)) {
            $text = get_string($visibilitycode, 'block_opencast', $video);
        }
        if (!empty($schedulingcode)) {
            if (!$schedulingresult) {
                $status = empty($visibilitycode) ? \core\output\notification::NOTIFY_ERROR :
                    \core\output\notification::NOTIFY_WARNING;
            }
            $schedulingtext = get_string($schedulingcode, 'block_opencast');
            $text = $text . (!empty($visibilitycode) ? '<br>' : '') . $schedulingtext;
        }

        // That happens when no changes are made to the visibility.
        if (empty($text)) {
            $text = get_string('novisibilitychange', 'block_opencast');
            $redirecturl = $baseurl;
            $status = \core\output\notification::NOTIFY_WARNING;
        }
        redirect($redirecturl, $text, null, $status);
    }
}

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
if (!empty($scheduledvisibility) && intval($scheduledvisibility->status) == visibility_helper::STATUS_FAILED) {
    echo $OUTPUT->notification(get_string('scheduledvisibilitychangefailed', 'block_opencast'), 'error');
}
echo $OUTPUT->heading(get_string('changevisibility_header', 'block_opencast', $video));
$changevisibilityform->display();
echo $OUTPUT->footer();
