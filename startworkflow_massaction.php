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
 * Start workflow - Mass action.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\apibridge;
use core\output\notification;
use tool_opencast\local\settings_api;
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $USER, $COURSE, $DB;

$ismassaction = required_param('ismassaction', PARAM_INT);
$videoids = required_param_array('videoids', PARAM_RAW);
$courseid = required_param('courseid', PARAM_INT);
$workflow = required_param('workflow', PARAM_ALPHANUMEXT);
$configparams = required_param('configparams', PARAM_RAW);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);

require_login($courseid, false);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:startworkflow', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);

$seriesid = $apibridge->get_default_course_series($courseid);
$apiworkflow = $apibridge->get_workflow_definition($workflow);

// Apply multiple tags.
$workflowtags = [];
$workflowtagsconfig = get_config('block_opencast', 'workflow_tags_' . $ocinstanceid);
if (!empty($workflowtagsconfig)) {
    $workflowtags = explode(',', $workflowtagsconfig);
    $workflowtags = array_map('trim', $workflowtags);
}
if (!$apiworkflow || empty(array_intersect($apiworkflow->tags, $workflowtags))) {
    redirect($redirecturl,
        get_string('workflow_opencast_invalid', 'block_opencast'),
        null,
        notification::NOTIFY_ERROR);
}

$seriesid = $apibridge->get_default_course_series($courseid);

$failed = [];
$succeeded = [];

foreach ($videoids as $videoid) {
    $video = $apibridge->get_opencast_video($videoid);
    $stringobj = new stdClass();
    $stringobj->name = $video->video->title;
    if ($seriesid->identifier != $video->video->is_part_of) {
        $stringobj->reason = get_string('video_notallowed', 'block_opencast');
        $failed[] = get_string('videostablemassaction_notification_reasoning', 'block_opencast', $stringobj);
        continue;
    }

    $result = $apibridge->start_workflow($videoid, $workflow, ['configuration' => $configparams]);

    if ($result) {
        $succeeded[] = $video->video->title;
    } else {
        $stringobj->reason = get_string('workflow_started_failure', 'block_opencast');
        $failed[] = get_string('videostablemassaction_notification_reasoning', 'block_opencast', $stringobj);
    }
}

$failedtext = '';
if (!empty($failed)) {
    $failedtext = get_string(
        'workflow_started_massaction_notification_failed',
        'block_opencast',
        implode('</li><li>', $failed)
    );
}
$succeededtext = '';
if (!empty($succeeded)) {
    $succeededtext = get_string(
        'workflow_started_massaction_notification_success',
        'block_opencast',
        implode('</li><li>', $succeeded)
    );
}

// If there is no changes, we redirect with warning.
if (empty($succeededtext) && empty($failedtext)) {
    $nochangetext = get_string('workflow_started_massaction_nochange', 'block_opencast');
    redirect($redirecturl, $nochangetext, null, notification::NOTIFY_ERROR);
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
