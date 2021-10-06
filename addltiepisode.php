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
 * Allows teachers to add a LTI activity for a video.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG;

// Handle submitted parameters of the form.
$episodeuuid = required_param('episodeuuid', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$submitbutton2 = optional_param('submitbutton2', '', PARAM_ALPHA);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id,PARAM_INT);

// Set base URL.
$baseurl = new moodle_url('/blocks/opencast/addltiepisode.php', array('episodeuuid' => $episodeuuid, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

// Remember URLs for redirecting.
$redirecturloverview = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$redirecturlcourse = new moodle_url('/course/view.php', array('id' => $courseid));
$redirecturlcancel = $redirecturloverview;

// Require login and course membership.
require_login($courseid, false);

// Set page and navbar properties.
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturloverview);
$PAGE->navbar->add(get_string('addltiepisode_addicontitle', 'block_opencast'), $baseurl);

// Check if the LTI module feature is enabled and working.
if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == false) {
    throw new moodle_exception('addltiepisode_errornotenabledorworking', 'block_opencast', $redirecturloverview);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addltiepisode', $coursecontext);

// Existing LTI module check.
$moduleid = \block_opencast\local\ltimodulemanager::get_module_for_episode($ocinstanceid, $courseid, $episodeuuid);
if ($moduleid) {
    // Redirect to Opencast videos overview page.
    redirect($redirecturloverview,
            get_string('addltiepisode_moduleexists', 'block_opencast'), null, \core\output\notification::NOTIFY_WARNING);
}

// Use Add LTI form.
$addltiform = new \block_opencast\local\addltiepisode_form(null, array('episodeuuid' => $episodeuuid, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

// Redirect if the form was cancelled.
if ($addltiform->is_cancelled()) {
    redirect($redirecturloverview);
}

// Process data.
if ($data = $addltiform->get_data()) {
    // Verify again that we have a title. If not, use the default title.
    if (!$data->title) {
        $data->title = get_string('addltiepisode_defaulttitle', 'block_opencast');
    }

    // If the intro feature is disabled or if we do not have an intro, use an empty string as intro.
    if (get_config('block_opencast', 'addltiepisodeintro_' . $ocinstanceid) != true || !isset($data->intro) || !$data->intro) {
        $introtext = '';
        $introformat = FORMAT_HTML;

        // Otherwise.
    } else {
        $introtext = $data->intro['text'];
        $introformat = $data->intro['format'];
    }

    // If the section feature is disabled or if we do not have an intro, use the default section.
    if (get_config('block_opencast', 'addltiepisodesection_' . $ocinstanceid) != true || !isset($data->section) || !$data->section) {
        $sectionid = 0;

        // Otherwise.
    } else {
        $sectionid = $data->section;
    }

    // If the availability feature is disabled or if we do not have an availability given, use null.
    if (get_config('block_opencast', 'addltiepisodeavailability_' . $ocinstanceid) != true || empty($CFG->enableavailability) ||
            !isset($data->availabilityconditionsjson) || !$data->availabilityconditionsjson) {
        $availability = null;

        // Otherwise.
    } else {
        $availability = $data->availabilityconditionsjson;
    }

    // Create the module.
    $result = \block_opencast\local\ltimodulemanager::create_module_for_episode($ocinstanceid, $courseid, $data->title, $episodeuuid,
            $sectionid, $introtext, $introformat, $availability);

    // Check if the module was created successfully.
    if ($result == true) {
        // Form was submitted with second submit button.
        if ($submitbutton2) {
            // Redirect to course overview.
            redirect($redirecturlcourse,
                    get_string('addltiepisode_modulecreated', 'block_opencast', $data->title),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS);

            // Form was submitted with first submit button.
        } else {
            // Redirect to Opencast videos overview page.
            redirect($redirecturloverview,
                    get_string('addltiepisode_modulecreated', 'block_opencast', $data->title),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS);
        }

        // Otherwise.
    } else {
        // Redirect to Opencast videos overview page.
        redirect($redirecturloverview,
                get_string('addltiepisode_modulenotcreated', 'block_opencast', $data->title),
                null,
                \core\output\notification::NOTIFY_ERROR);
    }
}

// Output the page header.
echo $OUTPUT->header();

// Output heading.
echo $OUTPUT->heading(get_string('addltiepisode_addicontitle', 'block_opencast'));

// Output the form.
$addltiform->display();

// Output the page footer.
echo $OUTPUT->footer();
