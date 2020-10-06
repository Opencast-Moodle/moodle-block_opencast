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
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG;

// Handle submitted parameters of the form.
$courseid = required_param('courseid', PARAM_INT);
$step = optional_param('step', 1, PARAM_INT);
$sourcecourseid = optional_param('sourcecourseid', null, PARAM_INT);
$coursevideos = optional_param_array('coursevideos', array(), PARAM_ALPHANUMEXT);
$fixseriesmodules = optional_param('fixseriesmodules', false, PARAM_BOOL);

// Set base URL.
$baseurl = new moodle_url('/blocks/opencast/importvideos.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

// Remember URLs for redirecting.
$redirecturloverview = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));
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
if (\block_opencast\local\importvideosmanager::is_enabled_and_working_for_manualimport() == false) {
    print_error('importvideos_errornotenabledorworking', 'block_opencast', $redirecturloverview);
}

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:manualimporttarget', $coursecontext);

// Check if the LTI series feature feature is enabled _and_ the user is allowed to use the feature,
// we have to include step 3.
if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_series() == true &&
        has_capability('block/opencast:addlti', $coursecontext)) {
    $hasstep3 = true;
} else {
    $hasstep3 = false;
}

// Deal with wizard step forms individually.
switch ($step) {
    default:
    case 1:
        // Use step 1 form.
        $importvideosform = new \block_opencast\local\importvideos_step1_form(null,
                array('courseid' => $courseid));

        // Redirect if the form was cancelled.
        if ($importvideosform->is_cancelled()) {
            redirect($redirecturlcancel);
        }

        // Process data.
        if ($data = $importvideosform->get_data()) {
            // Raise step variable (which is used for the progress bar and heading later on).
            $step += 1;

            // Replace form with next step.
            $importvideosform = new \block_opencast\local\importvideos_step2_form(null,
                    array('courseid' => $courseid,
                          'sourcecourseid' => $sourcecourseid));
        }

        break;
    case 2:
        // Use step 2 form.
        $importvideosform = new \block_opencast\local\importvideos_step2_form(null,
                array('courseid' => $courseid,
                      'sourcecourseid' => $sourcecourseid));

        // Redirect if the form was cancelled.
        if ($importvideosform->is_cancelled()) {
            redirect($redirecturlcancel);
        }

        // Process data.
        if ($data = $importvideosform->get_data()) {
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
                // Raise step variable (which is used for the progress bar and heading later on).
                $step += 1;

                // Replace form with next step.
                $importvideosform = new \block_opencast\local\importvideos_step3_form(null,
                        array('courseid' => $courseid,
                              'sourcecourseid' => $sourcecourseid,
                              'coursevideos' => $coursevideos));

                // Otherwise, we skip step 3 and go directly to step 4.
            } else {
                // Raise step variable (which is used for the progress bar and heading later on).
                $step += 2;

                // Replace form with next step.
                $importvideosform = new \block_opencast\local\importvideos_step4_form(null,
                        array('courseid' => $courseid,
                              'sourcecourseid' => $sourcecourseid,
                              'coursevideos' => $coursevideos));
            }
        }

        break;
    case 3:
        // Use step 3 form.

        $importvideosform = new \block_opencast\local\importvideos_step3_form(null,
                array('courseid' => $courseid,
                      'sourcecourseid' => $sourcecourseid,
                      'coursevideos' => $coursevideos));

        // Redirect if the form was cancelled.
        if ($importvideosform->is_cancelled()) {
            redirect($redirecturlcancel);
        }

        // Process data.
        if ($data = $importvideosform->get_data()) {
            // Raise step variable (which is used for the progress bar and heading later on).
            $step += 1;

            // Replace form with next step.
            $importvideosform = new \block_opencast\local\importvideos_step4_form(null,
                    array('courseid' => $courseid,
                          'sourcecourseid' => $sourcecourseid,
                          'coursevideos' => $coursevideos,
                          'fixseriesmodules' => $fixseriesmodules));
        }

        break;
    case 4:
        // Use step 4 form.
        $importvideosform = new \block_opencast\local\importvideos_step4_form(null,
                array('courseid' => $courseid,
                      'sourcecourseid' => $sourcecourseid,
                      'coursevideos' => $coursevideos,
                      'fixseriesmodules' => $fixseriesmodules));

        // Redirect if the form was cancelled.
        if ($importvideosform->is_cancelled()) {
            redirect($redirecturlcancel);
        }

        // Process data.
        if ($data = $importvideosform->get_data()) {
            // Duplicate the videos.
            $resultduplicate = \block_opencast\local\importvideosmanager::duplicate_videos($sourcecourseid, $courseid, $coursevideos);

            // If duplication did not complete correctly.
            if ($resultduplicate != true) {
                // Redirect to Opencast videos overview page without cleaning up any modules.
                redirect($redirecturloverview,
                        get_string('importvideos_importjobcreationfailed', 'block_opencast'),
                        null,
                        \core\output\notification::NOTIFY_ERROR);
            }

            // If cleanup of the series modules was requested.
            if ($fixseriesmodules == true) {
                // Clean up the series modules.
                $resulthandleseries = \block_opencast\local\ltimodulemanager::cleanup_series_modules($courseid, $sourcecourseid);

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

        break;
}

// Output the page header.
echo $OUTPUT->header();

// Output the progress bar.
echo \block_opencast\local\importvideosmanager::render_progress_bar($step, 4, $hasstep3);

// Output heading.
echo $OUTPUT->heading(get_string('importvideos_wizardstep'.$step.'heading', 'block_opencast'));

// Output the form.
$importvideosform->display();

// Output the page footer.
echo $OUTPUT->footer();
