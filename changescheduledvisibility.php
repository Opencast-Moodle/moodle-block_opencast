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
 * Change Scheduled Visibility.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('./renderer.php');

use block_opencast\local\apibridge;
use block_opencast\local\scheduledvisibility_form;
use block_opencast\local\visibility_helper;
use core\output\notification;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $DB;

$uploadjobid = required_param('uploadjobid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/changescheduledvisibility.php',
    ['uploadjobid' => $uploadjobid, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

$redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('changescheduledvisibilityheader', 'block_opencast'), $baseurl);

// Check if the ACL control feature is enabled.
if (get_config('block_opencast', 'aclcontrolafter_' . $ocinstanceid) != true) {
    throw new moodle_exception('ACL control feature not enabled', 'block_opencast', $redirecturl);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$scheduledvisibility = visibility_helper::get_uploadjob_scheduled_visibility($uploadjobid);
if (empty($scheduledvisibility)) {
    $message = get_string('novisibilityrecordfound', 'block_opencast');
    redirect($redirecturl, $message, null, notification::NOTIFY_ERROR);
}

$scheduledvisibilityform = new scheduledvisibility_form(null, ['courseid' => $courseid,
    'ocinstanceid' => $ocinstanceid, 'uploadjobid' => $uploadjobid, 'scheduledvisibility' => $scheduledvisibility, ]);

// Workflow is not set.
if (get_config('block_opencast', 'workflow_roles_' . $ocinstanceid) == "") {
    $message = get_string('workflownotdefined', 'block_opencast');
    redirect($redirecturl, $message, null, \core\notification::ERROR);
}

if ($scheduledvisibilityform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $scheduledvisibilityform->get_data()) {
    if (confirm_sesskey()) {
        $scheduledvisibility->scheduledvisibilitytime = $data->scheduledvisibilitytime;
        $scheduledvisibility->scheduledvisibilitystatus = $data->scheduledvisibilitystatus;
        $scheduledvisibilitygroups = null;
        if ($data->scheduledvisibilitystatus == block_opencast_renderer::GROUP
            && !empty($data->scheduledvisibilitygroups)) {
            $scheduledvisibilitygroups = json_encode($data->scheduledvisibilitygroups);
        }
        $scheduledvisibility->scheduledvisibilitygroups = $scheduledvisibilitygroups;
        $result = visibility_helper::update_visibility_job($scheduledvisibility);

        if ($result) {
            redirect($redirecturl, get_string('changescheduledvisibilitysuccess', 'block_opencast'), null,
                notification::NOTIFY_SUCCESS);
        } else {
            redirect($redirecturl, get_string('changescheduledvisibilityfailed', 'block_opencast'),
                null, notification::NOTIFY_ERROR);
        }
    }
}
// Try to extract the title from the upload job, which is stored in metadata table.
$metadatarecord = $DB->get_record('block_opencast_metadata', ['uploadjobid' => $uploadjobid]);
$metadata = !empty($metadatarecord->metadata) ? json_decode($metadatarecord->metadata) : [];
$title = '';
foreach ($metadata as $ms) {
    if ($ms->id == 'title') {
        $title = $ms->value;
    }
}
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('changescheduledvisibility', 'block_opencast', $title));
$scheduledvisibilityform->display();
echo $OUTPUT->footer();
