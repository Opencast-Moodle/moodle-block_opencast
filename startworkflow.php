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
 * Send problem report to support.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\apibridge;

global $PAGE, $OUTPUT, $CFG, $USER, $COURSE, $DB;

$courseid = required_param('courseid', PARAM_INT);
$videoid = required_param('videoid', PARAM_ALPHANUMEXT);
$workflow = required_param('workflow', PARAM_ALPHANUMEXT);
$configparams = required_param('configparams', PARAM_RAW);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

require_login($courseid, false);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:startworkflow', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);

// Check that video is in opencast series.
$video = $apibridge->get_opencast_video($videoid);
$seriesid = $apibridge->get_default_course_series($courseid);
if ($seriesid->identifier != $video->video->is_part_of) {
    redirect($redirecturl,
        get_string('video_notallowed', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}

$apiworkflow = $apibridge->get_workflow_definition($workflow);
// Apply multiple tags.
$workflowtags = array();
$workflowtagsconfig = get_config('block_opencast', 'workflow_tags_' . $ocinstanceid);
if (!empty($workflowtagsconfig)) {
    $workflowtags = explode(',', $workflowtagsconfig);
    $workflowtags = array_map('trim', $workflowtags);
}
if (!$apiworkflow or empty(array_intersect($apiworkflow->tags, $workflowtags))) {
    redirect($redirecturl,
        get_string('workflow_opencast_invalid', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}

$result = $apibridge->start_workflow($videoid, $workflow, array('configuration' => $configparams));

if ($result) {
    // Redirect with success message.
    redirect($redirecturl,
        get_string('workflow_started_success', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($redirecturl,
        get_string('workflow_started_failure', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}
