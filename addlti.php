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
 * Allows teachers to add a LTI activity for the block series.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\addlti_form;
use block_opencast\local\apibridge;
use block_opencast\local\ltimodulemanager;
use core\output\notification;
use tool_opencast\local\settings_api;

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $USER;

// Handle submitted parameters of the form.
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);
$submitbutton2 = optional_param('submitbutton2', '', PARAM_ALPHA);
$seriesid = required_param('seriesid', PARAM_ALPHANUMEXT);

// Set base URL.
$baseurl = new moodle_url('/blocks/opencast/addlti.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$PAGE->set_url($baseurl);

// Remember URLs for redirecting.
$redirecturloverview = new moodle_url('/blocks/opencast/index.php',
    ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$redirecturlcourse = new moodle_url('/course/view.php', ['id' => $courseid]);
$redirecturlcancel = $redirecturloverview;

// Require login and course membership.
require_login($courseid, false);

// Set page and navbar properties.
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturloverview);
$PAGE->navbar->add(get_string('addlti_addbuttontitle', 'block_opencast'), $baseurl);

// Check if the LTI module feature is enabled and working.
if (ltimodulemanager::is_enabled_and_working_for_series($ocinstanceid) == false) {
    throw new moodle_exception('addlti_errornotenabledorworking', 'block_opencast', $redirecturloverview);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addlti', $coursecontext);

// Existing LTI module check.
$moduleid = ltimodulemanager::get_module_for_series($ocinstanceid, $courseid, $seriesid);
if ($moduleid) {
    // Redirect to Opencast videos overview page.
    redirect($redirecturloverview,
        get_string('addlti_moduleexists', 'block_opencast'), null, notification::NOTIFY_WARNING);
}

// Use Add LTI form.
$addltiform = new addlti_form(null,
    ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid, 'seriesid' => $seriesid]);

// Get API bridge instance.
$apibridge = apibridge::get_instance($ocinstanceid);

// Redirect if the form was cancelled.
if ($addltiform->is_cancelled()) {
    redirect($redirecturloverview);
}

// Process data.
if ($data = $addltiform->get_data()) {
    // Verify again that we have a title. If not, use the default title.
    if (!$data->title) {
        $data->title = get_string('addlti_defaulttitle', 'block_opencast');
    }

    // If the intro feature is disabled or if we do not have an intro, use an empty string as intro.
    if (get_config('tool_opencast', 'addltiintro_' . $ocinstanceid) != true || !isset($data->intro) || !$data->intro) {
        $introtext = '';
        $introformat = FORMAT_HTML;

        // Otherwise.
    } else {
        $introtext = $data->intro['text'];
        $introformat = $data->intro['format'];
    }

    // If the section feature is disabled or if we do not have an intro, use the default section.
    if (get_config('tool_opencast', 'addltisection_' . $ocinstanceid) != true || !isset($data->section) || !$data->section) {
        $sectionid = 0;

        // Otherwise.
    } else {
        $sectionid = $data->section;
    }

    // If the availability feature is disabled or if we do not have an availability given, use null.
    if (get_config('tool_opencast', 'addltiavailability_' . $ocinstanceid) != true || empty($CFG->enableavailability) ||
        !isset($data->availabilityconditionsjson) || !$data->availabilityconditionsjson) {
        $availability = null;

        // Otherwise.
    } else {
        $availability = $data->availabilityconditionsjson;
    }

    // Create the module.
    $result = ltimodulemanager::create_module_for_series($ocinstanceid, $courseid,
        $data->title, $data->seriesid, $sectionid, $introtext, $introformat, $availability);

    // Check if the module was created successfully.
    if ($result == true) {
        // Form was submitted with second submit button.
        if ($submitbutton2) {
            // Redirect to course overview.
            redirect($redirecturlcourse,
                get_string('addlti_modulecreated', 'block_opencast', $data->title),
                null,
                notification::NOTIFY_SUCCESS);

            // Form was submitted with first submit button.
        } else {
            // Redirect to Opencast videos overview page.
            redirect($redirecturloverview,
                get_string('addlti_modulecreated', 'block_opencast', $data->title),
                null,
                notification::NOTIFY_SUCCESS);
        }

        // Otherwise.
    } else {
        // Redirect to Opencast videos overview page.
        redirect($redirecturloverview,
            get_string('addlti_modulenotcreated', 'block_opencast', $data->title),
            null,
            notification::NOTIFY_ERROR);
    }
}

// Output the page header.
echo $OUTPUT->header();

// Output heading.
echo $OUTPUT->heading(get_string('addlti_addbuttontitle', 'block_opencast'));

// Output the form.
$addltiform->display();

// Output the page footer.
echo $OUTPUT->footer();
