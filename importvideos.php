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
 * Page to manually import videos from other Moodle courses.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\activitymodulemanager;

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $DB;

// Handle submitted parameters of the form.
// This course id of the target course.
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);
// The current step of the wizard.
$step = optional_param('step', 1, PARAM_INT);
// The course id of the course where the videos are imported from (this is submitted by the course search component in step 1 only).
$importid = optional_param('importid', null, PARAM_INT);
// The course id of the course where the videos are imported from (with this variable we carry the id though the wizard).
$sourcecourseid = optional_param('sourcecourseid', null, PARAM_INT);
$sourcecourseseries = optional_param('sourcecourseseries', null, PARAM_ALPHANUMEXT);

// The list of course videos to import.
$coursevideos = optional_param_array('coursevideos', array(), PARAM_ALPHANUMEXT);

// The fact if we have to handle series and / or episode modules after the import.
$fixseriesmodules = optional_param('fixseriesmodules', false, PARAM_BOOL);
$fixepisodemodules = optional_param('fixepisodemodules', false, PARAM_BOOL);

// Set base URL.
$baseurl = new moodle_url('/blocks/opencast/importvideos.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

// Remember URLs for redirecting.
$redirecturloverview = new moodle_url('/blocks/opencast/index.php',
    array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$redirecturlcancel = $redirecturloverview;

// Require login and course membership.
require_login($courseid, false);

// Set page and navbar properties.
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturloverview);
$PAGE->navbar->add(get_string('importvideos_importbuttontitle', 'block_opencast'), $baseurl);

// Check if the manual import videos feature is enabled and working.
if (\block_opencast\local\importvideosmanager::is_enabled_and_working_for_manualimport($ocinstanceid) == false) {
    throw new moodle_exception('importvideos_errornotenabledorworking', 'block_opencast', $redirecturloverview);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:manualimporttarget', $coursecontext);

// Check if either the handle series feature or the handle episodes feature is enabled _and_ the user is allowed to use the feature,
// we have to include step 3.
if ((\block_opencast\local\importvideosmanager::handle_series_modules_is_enabled_and_working($ocinstanceid) == true &&
        has_capability('block/opencast:addlti', $coursecontext)) ||
    (\block_opencast\local\importvideosmanager::handle_episode_modules_is_enabled_and_working($ocinstanceid) == true &&
        has_capability('block/opencast:addltiepisode', $coursecontext))) {
    $hasstep3 = true;
} else {
    $hasstep3 = false;
}

// Defining totalsteps, in order to use it for different import modes.
$totalsteps = 4;
// Get import mode from the admin setting.
$importmode = get_config('block_opencast', 'importmode_' . $ocinstanceid);
// In case of ACL change import mode.
if ($importmode == 'acl') {
    // Total steps in ACL Change mode is 2, where only a course should be selected and a summary should be displayed.
    $totalsteps = 3;
    // We need a normal 2 Steps progress bar.
    $hasstep3 = true;

    // Check if the maximum number of series is already reached.
    $courseseries = $DB->get_records('tool_opencast_series', array('ocinstanceid' => $ocinstanceid, 'courseid' => $courseid));
    if (count($courseseries) >= get_config('block_opencast', 'maxseries_' . $ocinstanceid)) {
        throw new moodle_exception('maxseriesreached', 'block_opencast');
    }
}

// Get renderer.
$renderer = $PAGE->get_renderer('block_opencast', 'importvideos');
$importvideosform = null;

// Deal with wizard step forms individually.
$jumpto4 = false;
switch ($step) {
    default:
    case 1:
        $nextstep = false;

        // While we use custom mforms in step 2 to 3, we rely on a Moodle core component in step 1.
        // That's why step 1 is structured differently than the following steps.

        // If there isn't any other course which can be used as import source.
        $possiblesourcecourses = get_user_capability_course(
            'block/opencast:manualimportsource');
        $possiblesourcecoursescount = count($possiblesourcecourses);
        if ($possiblesourcecoursescount < 1 || ($possiblesourcecoursescount == 1 && $possiblesourcecourses[0]->id == $courseid)) {
            // Use step 1 form.
            $importvideosform = new \block_opencast\local\importvideos_step1_form(null,
                array('courseid' => $courseid));

            // Redirect if the form was cancelled.
            if ($importvideosform->is_cancelled()) {
                redirect($redirecturlcancel);
            }

            $heading = get_string('importvideos_wizardstep' . $step . 'heading', 'block_opencast');

        } else {
            // If a course was not selected yet with the course search component.
            if ($importid == null) {
                // Prepare next step URL.
                $url = new moodle_url('/blocks/opencast/importvideos.php',
                    array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

                // Prepare course search component.
                $search = new \block_opencast\local\importvideos_coursesearch(array('url' => $url), $courseid);

                $heading = get_string('importvideos_wizardstep' . $step . 'heading', 'block_opencast');
                // Output course search component.
                $body = $renderer->importvideos_coursesearch($url, $search);
            }

            // If a course was selected with the course search component.
            if ($importid != null) {
                $sourcecourseid = $importid;
                $nextstep = true;
            }
        }

        if (!$nextstep) {
            break;
        }
    case 2:
        $nextstep = false;
        $step = 2;
        $aclheadingstringname = '';

        // When the Duplicating Events is selected.
        if ($importmode == 'duplication') {
            // Set form for next step.
            $importvideosform = new \block_opencast\local\importvideos_step2_form(null,
                array(
                    'ocinstanceid' => $ocinstanceid,
                    'courseid' => $courseid,
                    'sourcecourseid' => $sourcecourseid));
        } else if ($importmode == 'acl') {
            $aclheadingstringname = 'acl';

            $courseseries = \block_opencast\local\importvideosmanager::get_import_source_course_series($ocinstanceid,
                $sourcecourseid);

            if (count($courseseries) > 1) {
                $importvideosform = new \block_opencast\local\importvideos_select_series_form(null, array(
                    'ocinstanceid' => $ocinstanceid,
                    'courseid' => $courseid,
                    'sourcecourseid' => $sourcecourseid,
                    'series' => $courseseries));
            } else {
                $nextstep = true;
            }
        }

        if (!$nextstep) {
            // Redirect if the form was cancelled.
            if ($importvideosform->is_cancelled()) {
                redirect($redirecturlcancel);
            }

            // Process data.
            if ($data = $importvideosform->get_data()) {
                if ($importmode == 'duplication') {
                    // If we are in Duplicating Events mode.
                    // Process the array of course videos.
                    foreach ($coursevideos as $identifier => $checked) {
                        // Check if the video was not selected (which may happen as we are using an advcheckbox element).
                        if ($checked != 1) {
                            // Remove the video from the array of coursevideos.
                            unset($coursevideos[$identifier]);
                        }
                    }

                    // If we have to include step 3 now.
                    if ($hasstep3 == true) {
                        $nextstep = true;
                    } else {
                        $nextstep = true;
                        $jumpto4 = true;
                    }
                } else {
                    $sourcecourseseries = $data->series;
                    $nextstep = true;
                }
            }

            // Output heading.
            $heading = get_string('importvideos_wizardstep' . $step . $aclheadingstringname . 'heading', 'block_opencast');
        }

        if (!$nextstep) {
            break;
        }

    case 3:
        $nextstep = false;
        $step = 3;

        if (!$jumpto4) {

            if ($importmode == 'acl') {
                $importvideosform = new \block_opencast\local\importvideos_step3_form_acl(null,
                    array(
                        'ocinstanceid' => $ocinstanceid,
                        'courseid' => $courseid,
                        'sourcecourseid' => $sourcecourseid,
                        'series' => $sourcecourseseries));
            } else {
                $importvideosform = new \block_opencast\local\importvideos_step3_form(null,
                    array(
                        'ocinstanceid' => $ocinstanceid,
                        'courseid' => $courseid,
                        'sourcecourseid' => $sourcecourseid,
                        'coursevideos' => $coursevideos));
            }


            // Redirect if the form was cancelled.
            if ($importvideosform->is_cancelled()) {
                redirect($redirecturlcancel);
            }

            // Process data.
            if ($data = $importvideosform->get_data()) {

                if ($importmode == 'acl') {
                    // Perform ACL change.
                    $resultaclchange = \block_opencast\local\importvideosmanager::change_acl($ocinstanceid, $sourcecourseid,
                        $data->sourcecourseseries, $courseid);
                    // Redirec the user with corresponding messages.
                    redirect($redirecturloverview, $resultaclchange->message, null, $resultaclchange->type);
                } else {
                    $nextstep = true;
                }
            }

            // Output heading.
            $heading = get_string('importvideos_wizardstep' . $step . 'heading', 'block_opencast');

            if (!$nextstep) {
                break;
            }
        }
    case 4:
        $step = 4;
        // Use step 4 form.
        $importvideosform = new \block_opencast\local\importvideos_step4_form(null,
            array(
                'ocinstanceid' => $ocinstanceid,
                'courseid' => $courseid,
                'sourcecourseid' => $sourcecourseid,
                'coursevideos' => $coursevideos,
                'fixseriesmodules' => $fixseriesmodules,
                'fixepisodemodules' => $fixepisodemodules));

        // Redirect if the form was cancelled.
        if ($importvideosform->is_cancelled()) {
            redirect($redirecturlcancel);
        }

        // Process data.
        if ($data = $importvideosform->get_data()) {
            $resultduplicate = \block_opencast\local\importvideosmanager::duplicate_videos($ocinstanceid,
                $sourcecourseid, $courseid, $coursevideos, $fixepisodemodules);

            // If duplication did not complete correctly.
            if ($resultduplicate != true) {
                // Redirect to Opencast videos overview page without cleaning up any modules.
                redirect($redirecturloverview,
                    get_string('importvideos_importjobcreationfailed', 'block_opencast'),
                    null,
                    \core\output\notification::NOTIFY_ERROR);
            }

            // If cleanup of the series modules was requested and the user is allowed to do this.
            if ($fixseriesmodules == true) {
                $resulthandleseries = true;
                if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_series($ocinstanceid) &&
                    has_capability('block/opencast:addlti', $coursecontext)) {
                    // Clean up the series modules.
                    $resulthandleseries = \block_opencast\local\ltimodulemanager::cleanup_series_modules($ocinstanceid,
                        $courseid, $sourcecourseid);
                }

                if (\core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null) {
                    $resulthandleseries = $resulthandleseries &&
                        activitymodulemanager::cleanup_series_modules($ocinstanceid, $courseid, $sourcecourseid);
                }

                // If clean up did not completed correctly.
                if ($resulthandleseries != true) {
                    // Redirect to Opencast videos overview page with an error notification.
                    redirect($redirecturloverview,
                        get_string('importvideos_importseriescleanupfailed', 'block_opencast'),
                        null,
                        \core\output\notification::NOTIFY_ERROR);
                }
            }

            // Everything seems to be fine, redirect to Opencast videos overview page.
            redirect($redirecturloverview,
                get_string('importvideos_importjobcreated', 'block_opencast'),
                null,
                \core\output\notification::NOTIFY_SUCCESS);
        }

        $heading = get_string('importvideos_wizardstep' . $step . 'heading', 'block_opencast');
        break;
}

// Output the page header.
echo $OUTPUT->header();

// Output the progress bar.
echo $renderer->progress_bar($step, $totalsteps, $hasstep3);

// Output heading.
echo $OUTPUT->heading($heading);

// Output the form.
if ($importvideosform) {
    $importvideosform->display();
} else {
    // Only used in step 1.
    echo $body;
}

// Output the page footer.
echo $OUTPUT->footer();
