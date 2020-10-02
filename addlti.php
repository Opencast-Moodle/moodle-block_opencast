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
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG;

$courseid = required_param('courseid', PARAM_INT);
$submitbutton2 = optional_param('submitbutton2', '', PARAM_ALPHA);

$baseurl = new moodle_url('/blocks/opencast/addlti.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$redirecturloverview = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));
$redirecturlcourse = new moodle_url('/course/view.php', array('id' => $courseid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturloverview);
$PAGE->navbar->add(get_string('addlti_addbuttontitle', 'block_opencast'), $baseurl);

// Check if the LTI module feature is enabled and working.
if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_series() == false) {
    print_error('add lti series module not enabled or working', 'block_opencast', $redirecturloverview);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addlti', $coursecontext);

// Existing LTI module check.
$moduleid = \block_opencast\local\ltimodulemanager::get_module_for_series($courseid);
if ($moduleid) {
    // Redirect to Opencast videos overview page.
    redirect($redirecturloverview,
            get_string('addlti_moduleexists', 'block_opencast'), null, \core\output\notification::NOTIFY_WARNING);
}

$addltiform = new \block_opencast\local\addlti_form(null, array('courseid' => $courseid));

$apibridge = \block_opencast\local\apibridge::get_instance();

if ($addltiform->is_cancelled()) {
    redirect($redirecturlcancel);
}

if ($data = $addltiform->get_data()) {
    // Verify again that we have a title. If not, use the default title.
    if (!$data->title) {
        $data->title = get_string('addlti_defaulttitle', 'block_opencast');
    }

    // If the intro feature is disabled or if we do not have an intro, use an empty string as intro.
    if (get_config('block_opencast', 'addltiintro') != true || !isset($data->intro) || !$data->intro) {
        $introtext = '';
        $introformat = FORMAT_HTML;

        // Otherwise.
    } else {
        $introtext = $data->intro['text'];
        $introformat = $data->intro['format'];
    }

    // If the section feature is disabled or if we do not have an intro, use the default section.
    if (get_config('block_opencast', 'addltisection') != true || !isset($data->section) || !$data->section) {
        $sectionid = 0;

        // Otherwise.
    } else {
        $sectionid = $data->section;
    }

    // If the availability feature is disabled or if we do not have an availability given, use null.
    if (get_config('block_opencast', 'addltiavailability') != true || empty($CFG->enableavailability) ||
            !isset($data->availabilityconditionsjson) || !$data->availabilityconditionsjson) {
        $availability = null;

        // Otherwise.
    } else {
        $availability = $data->availabilityconditionsjson;
    }

    // Get series ID, create a new one if necessary.
    $seriesid = $apibridge->get_stored_seriesid($courseid, true);

    // Create the module.
    $result = \block_opencast\local\ltimodulemanager::create_module_for_series($courseid, $data->title, $seriesid,
            $sectionid, $introtext, $introformat, $availability);

    // Check if the module was created successfully.
    if ($result == true) {
        // Form was submitted with second submit button.
        if ($submitbutton2) {
            // Redirect to course overview.
            redirect($redirecturlcourse,
                    get_string('addlti_modulecreated', 'block_opencast', $data->title),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS);

            // Form was submitted with first submit button.
        } else {
            // Redirect to Opencast videos overview page.
            redirect($redirecturloverview,
                    get_string('addlti_modulecreated', 'block_opencast', $data->title),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS);
        }

        // Otherwise.
    } else {
        // Redirect to Opencast videos overview page.
        redirect($redirecturloverview,
                get_string('addlti_modulenotcreated', 'block_opencast', $data->title),
                null,
                \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addlti_addbuttontitle', 'block_opencast'));

$addltiform->display();
echo $OUTPUT->footer();
