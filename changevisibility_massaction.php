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
 * Change Visibility - Mass Action
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('./renderer.php');

use block_opencast\groupaccess;
use block_opencast\local\apibridge;
use block_opencast\local\visibility_form_massaction;
use block_opencast\local\visibility_helper;
use core\output\notification;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG;

$ismassaction = required_param('ismassaction', PARAM_INT);
$videoids = required_param_array('videoids', PARAM_RAW);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/changevisibility_massaction.php',
    ['ismassaction' => $ismassaction, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

$redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('changevisibility_massaction', 'block_opencast'), $baseurl);

// Check if the ACL control feature is enabled.
if (get_config('block_opencast', 'aclcontrolafter_' . $ocinstanceid) != true) {
    throw new moodle_exception('ACL control feature not enabled', 'block_opencast', $redirecturl);
}

// Workflow is not set.
if (get_config('block_opencast', 'workflow_roles_' . $ocinstanceid) == "") {
    $message = get_string('workflownotdefined', 'block_opencast');
    redirect($redirecturl, $message, null, \core\notification::ERROR);
}

if (empty($videoids)) {
    $message = get_string('changevisibility_massaction_novideos', 'block_opencast');
    redirect($redirecturl, $message, null, \core\notification::ERROR);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);

$videosdatalist = [];

$courseseries = $apibridge->get_course_series($courseid);
foreach ($videoids as $videoid) {

    // Record the video data for later use.
    $videodata = new stdClass();
    $videodata->identifier = $videoid;
    $videodata->title = $videoid;
    $videodata->detail = null;
    $videodata->error = false;
    $videodata->visibility = null;
    $videodata->scheduledvisibility = null;

    $video = $apibridge->get_opencast_video($videoid);
    if (!empty($video->error)) {
        $videodata->error = get_string('videonotfound', 'block_opencast');
        $videodata->detail = get_string('changevisibility_massaction_videoerror', 'block_opencast', $videodata);
        $videosdatalist[] = $videodata;
        continue;
    }

    $videodata->title = $video->video->title;

    if (!in_array($video->video->processing_state, ["SUCCEEDED", "FAILED", "STOPPED"])) {
        $videodata->error = get_string('massaction_videostatusmismatched', 'block_opencast');
        $videodata->detail = get_string('changevisibility_massaction_videoerror', 'block_opencast', $videodata);
        $videosdatalist[] = $videodata;
        continue;
    }

    $visibility = $apibridge->is_event_visible($videoid, $courseid);
    if ($visibility === block_opencast_renderer::MIXED_VISIBILITY) {
        $groups = groupaccess::get_record(['opencasteventid' => $videoid, 'ocinstanceid' => $ocinstanceid]);
        if ($groups) {
            $visibility = block_opencast_renderer::GROUP;
        } else {
            $visibility = block_opencast_renderer::HIDDEN;
        }
    }

    $videodata->visibility = $visibility;
    list($visibilitystatus, $visibilitystatusdesc) = visibility_helper::get_visibility_status_legend($visibility);

    // To record lang string object and info.
    $langstringkey = 'changevisibility_massaction_visibility_status';
    $strobj = new stdClass();
    $strobj->title = $videodata->title;
    $strobj->vstatus = $visibilitystatus;
    $strobj->vstatusdesc = $visibilitystatusdesc;

    $scheduledvisibility = visibility_helper::get_event_scheduled_visibility($ocinstanceid, $courseid, $videoid);

    $videodata->scheduledvisibility = $scheduledvisibility;

    if (!empty($scheduledvisibility) && intval($scheduledvisibility->status) === visibility_helper::STATUS_PENDING) {
        $langstringkey = 'changevisibility_massaction_visibility_status_with_scheduled';

        list($scheduledvisibilitystatus, $scheduledvisibilitystatusdesc) =
            visibility_helper::get_visibility_status_legend($scheduledvisibility->scheduledvisibilitystatus);
        $scheduledvisibilitydatetime = userdate(
            $scheduledvisibility->scheduledvisibilitytime,
            get_string('strftimedatetime', 'langconfig')
        );

        $strobj->svstatus = $scheduledvisibilitystatus;
        $strobj->svstatusdesc = $scheduledvisibilitystatusdesc;
        $strobj->svdatetime = $scheduledvisibilitydatetime;
    }

    $videodata->detail = get_string($langstringkey, 'block_opencast', $strobj);

    $videosdatalist[] = $videodata;
}

$massactionchangevisibilityform = new visibility_form_massaction(null, ['courseid' => $courseid,
    'videosdatalist' => $videosdatalist, 'ocinstanceid' => $ocinstanceid, ]);

if ($massactionchangevisibilityform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $massactionchangevisibilityform->get_data()) {
    if (confirm_sesskey()) {

        $nochanges = [];

        $failed = [];
        $succeeded = [];

        $schedulingfailed = [];
        $schedulingsucceeded = [];

        $requestscheduling = false;
        if (isset($data->enableschedulingchangevisibility) && boolval($data->enableschedulingchangevisibility)) {
            $requestscheduling = true;
        }

        $groups = null;
        if (property_exists($data, 'groups')) {
            $groups = $data->groups;
        }

        $initialvisibilitygroups = null;
        if ($data->visibility == block_opencast_renderer::GROUP
            && !empty($groups)) {
            $initialvisibilitygroups = json_encode($groups);
        }
        $scheduledvisibilitygroups = null;
        if ($data->scheduledvisibilitystatus == block_opencast_renderer::GROUP
            && !empty($data->scheduledvisibilitygroups)) {
            $scheduledvisibilitygroups = json_encode($data->scheduledvisibilitygroups);
        }

        // All processed video data is included in $videosdatalist variable beforehand!
        foreach ($videosdatalist as $videodata) {

            $haschanges = false;

            // Just skip if the video has any error!
            if (!empty($videodata->error)) {
                $failed[$videodata->identifier] =
                    get_string('changevisibility_massaction_report_failed', 'block_opencast', $videodata);
                continue;
            }

            // If only the requested visibility is different.
            if ($videodata->visibility != $data->visibility) {
                $changeresult = $apibridge->change_visibility($videodata->identifier, $courseid, $data->visibility, $groups);
                // If there is any error, we skip the scheduling process and mark this video as failed.

                if ($changeresult === false) {
                    $langstrkey = 'changevisibility_massaction_aclchangeerror';
                    if ($requestscheduling) {
                        $langstrkey = 'changevisibility_massaction_aclchangeerror_noscheduling';
                    }
                    $videodata->error = get_string($langstrkey, 'block_opencast');
                    $failed[] = get_string('changevisibility_massaction_report_failed', 'block_opencast', $videodata);
                    continue;
                }
                $succeeded[] = $videodata->title;
                $haschanges = true;
            }

            // $schedulingcode = '';
            $schedulingresult = true;
            // Check if the scheduled visibility is set, we update the record.
            if ($requestscheduling) {
                $scheduledvisibility = $videodata->scheduledvisibility;
                // If the record already exists, we update it.
                if (!empty($scheduledvisibility)) {
                    $scheduledvisibility->scheduledvisibilitytime = $data->scheduledvisibilitytime;
                    $scheduledvisibility->scheduledvisibilitystatus = $data->scheduledvisibilitystatus;
                    $scheduledvisibility->scheduledvisibilitygroups = $scheduledvisibilitygroups;
                    $schedulingresult = visibility_helper::update_visibility_job($scheduledvisibility);
                    if (!$schedulingresult) {
                        $videodata->error = get_string('scheduledvisibilityupdatefailed', 'block_opencast');
                    }
                } else {
                    // Otherwise, we create a new record.
                    $scheduledvisibility = new stdClass();
                    $scheduledvisibility->initialvisibilitystatus = $data->visibility;
                    $scheduledvisibility->initialvisibilitygroups = $initialvisibilitygroups;
                    $scheduledvisibility->scheduledvisibilitytime = $data->scheduledvisibilitytime;
                    $scheduledvisibility->scheduledvisibilitystatus = $data->scheduledvisibilitystatus;
                    $scheduledvisibility->scheduledvisibilitygroups = $scheduledvisibilitygroups;
                    $scheduledvisibility->ocinstanceid = $ocinstanceid;
                    $scheduledvisibility->courseid = $courseid;
                    $scheduledvisibility->opencasteventid = $videodata->identifier;
                    $schedulingresult = visibility_helper::save_visibility_job($scheduledvisibility);
                    if (!$schedulingresult) {
                        $videodata->error = get_string('scheduledvisibilitycreatefailed', 'block_opencast');
                    }
                }

                if (!$schedulingresult) {
                    $schedulingfailed[] = get_string('changevisibility_massaction_report_failed', 'block_opencast', $videodata);
                } else {
                    $schedulingsucceeded[] = $videodata->title;
                }

                $haschanges = true;
            }

            if (!$haschanges) {
                $nochanges[] = $videodata->title;
            }
        }

        // We notify those have no change first as info.
        if (!empty($nochanges)) {
            $nochangestext = get_string(
                'changevisibility_massaction_notification_nochanges',
                'block_opencast',
                implode('</li><li>', $nochanges)
            );
            \core\notification::add($nochangestext, \core\notification::INFO);
        }

        // We notify the errors of scheduling.
        if (!empty($schedulingfailed)) {
            $schedulingfailedtext = get_string(
                'changevisibility_massaction_notification_schedulingfailed',
                'block_opencast',
                implode('</li><li>', $schedulingfailed)
            );
            \core\notification::add($schedulingfailedtext, \core\notification::ERROR);
        }

        // We notify the succeeded scheduling.
        if (!empty($schedulingsucceeded)) {
            $schedulingsucceededtext = get_string(
                'changevisibility_massaction_notification_schedulingsucceeded',
                'block_opencast',
                implode('</li><li>', $schedulingsucceeded)
            );
            \core\notification::add($schedulingsucceededtext, \core\notification::SUCCESS);
        }

        $failedtext = '';
        if (!empty($failed)) {
            $failedtext = get_string(
                'changevisibility_massaction_notification_failed',
                'block_opencast',
                implode('</li><li>', $failed)
            );
        }
        $succeededtext = '';
        if (!empty($succeeded)) {
            $succeededtext = get_string(
                'changevisibility_massaction_notification_succeeded',
                'block_opencast',
                implode('</li><li>', $succeeded)
            );
        }

        // Redirect with error if no success message is available.
        if (empty($succeededtext) && !empty($failedtext)) {
            redirect($redirecturl, $failedtext, null, notification::NOTIFY_ERROR);
        }

        // Otherwise, notify the error message if exists.
        if (!empty($failedtext)) {
            \core\notification::add($failedtext, \core\notification::ERROR);
        }

        // If hitting here, that means success message exists and we can redirect!
        redirect($redirecturl, $succeededtext, null, notification::NOTIFY_SUCCESS);
    }
}

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('changevisibility_header_massaction', 'block_opencast', $video));
$massactionchangevisibilityform->display();
echo $OUTPUT->footer();
