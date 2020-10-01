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
 * Import videos management for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Import videos management for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importvideosmanager {

    /**
     * Helperfunction to get the status of the manual import videos feature.
     * This consists of a check if the feature is enabled by the admin and a check if a duplicate workflow is configured.
     *
     * @return boolean
     */
    public static function is_enabled_and_working_for_manualimport() {
        // Get the status of the whole import featureset.
        $config = get_config('block_opencast', 'importvideosenabled');

        // If the setting is false, then the featureset is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the status of the subfeature.
        $config = get_config('block_opencast', 'importvideosmanualenabled');

        // If the setting is false, then the subfeature is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the configured duplicate workflow.
        $workflow = get_config('block_opencast', 'duplicateworkflow');

        // If the workflow is empty, then the feature is not working.
        if (empty($workflow)) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Verify that the workflow exists in Opencast.
        $workflowexists = $apibridge->check_if_workflow_exists($workflow);

        // If the workflow does not exist, then the feature is not working.
        if (!$workflowexists) {
            // Inform the caller.
            return false;
        }

        // The feature should be working.
        return true;
    }

    /**
     * Helperfunction to get the status of the import videos within Moodle core course import wizard feature.
     * This consists of a check if the feature is enabled by the admin and a check if a duplicate workflow is configured.
     *
     * @return boolean
     */
    public static function is_enabled_and_working_for_coreimport() {
        // Get the status of the whole import featureset.
        $config = get_config('block_opencast', 'importvideosenabled');

        // If the setting is false, then the featureset is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the status of the subfeature.
        $config = get_config('block_opencast', 'importvideoscoreenabled');

        // If the setting is false, then the subfeature is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the configured duplicate workflow.
        $workflow = get_config('block_opencast', 'duplicateworkflow');

        // If the workflow is empty, then the feature is not working.
        if (empty($workflow)) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Verify that the workflow exists in Opencast.
        $workflowexists = $apibridge->check_if_workflow_exists($workflow);

        // If the workflow does not exist, then the feature is not working.
        if (!$workflowexists) {
            // Inform the caller.
            return false;
        }

        // The feature should be working.
        return true;
    }

    /**
     * Helperfunction to get the list of available workflows to be used by the import videos feature.
     * Please note that this function does not filter for workflows targeted at duplicated events,
     * it just fetches all available workflows.
     *
     * @param bool $withnoworkflow Add a 'no workflow' item to the list of workflows
     *
     * @return string
     */
    public static function get_available_workflows($withnoworkflow = false) {
        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Get the workflow list.
        $workflows = $apibridge->get_existing_workflows('api');

        // If requested, add the 'no workflow' item to the list of workflows.
        if ($withnoworkflow == true) {
            $noworkflow = [null => get_string('adminchoice_noworkflow', 'block_opencast')];
            $workflows = array_merge($noworkflow, $workflows);
        }

        // Finally, return the list of workflows.
        return $workflows;
    }

    /**
     * Helperfunction to get the list of courses which can be used as import source during manual import.
     * This function fetches all courses
     * a) where I am allowed to import videos from
     * b) which have Opencast videos.
     *
     * @param int $targetcourseid The target course id.
     *
     * @return array
     */
    public static function get_import_source_courses_menu($targetcourseid) {
        // Initialize source courses as empty array;
        $sourcecourses = array();

        // Get all the courses which the user is allowed to import courses from.
        // TODO Currently the list is limited to 100 courses, sorted by ID desc. This will be done in a better way
        // in a future commit.
        $courses = get_user_capability_course('block/opencast:manualimportsource', null, true, 'fullname', 'id DESC', 100);

        // If we didn't get any courses, return.
        if (count($courses) < 1) {
            return $sourcecourses;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Move the courses from the array of objects into the source courses array to be used by the caller.
        foreach ($courses as $key => $course) {
            // Are we looking at the target course now?
            if ($course->id == $targetcourseid) {
                // We don't need this course in the list.
                continue;
            }

            // Get course videos which are qualified to be imported.
            $coursebackupvideos = $apibridge->get_course_videos_for_backup($course->id);

            // If there are any videos.
            if (count($coursebackupvideos) > 0) {
                // Add course to source courses array.
                $optionstring = get_string('importvideos_wizardstep1sourcecourseoption', 'block_opencast',
                        array('id' => $course->id, 'fullname' => $course->fullname));
                $sourcecourses[$course->id] = $optionstring;
            }
        }

        // Finally, return the list of source courses.
        return $sourcecourses;
    }

    /**
     * Helperfunction to get the list of course videos which are stored in the given source course to be selected during manual import.
     *
     * @param int $sourcecourseid The source course id.
     *
     * @return array
     */
    public static function get_import_source_course_videos_menu($sourcecourseid) {
        // Initialize course videos as empty array;
        $coursevideos = array();

        // If the user is not allowed to import from the given course at all, return.
        $coursecontext = \context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $coursecontext) != true) {
            return $coursevideos;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Get course videos which are qualified to be imported.
        $coursebackupvideos = $apibridge->get_course_videos_for_backup($sourcecourseid);

        // If there aren't any videos, return.
        if (count($coursebackupvideos) < 1) {
            return $coursevideos;
        }

        // Move the course videos from the array of objects into the course videos array to be used by the caller.
        // During this movement, render the course video menu entry with all necessary information.
        foreach ($coursebackupvideos as $identifier => $video) {
            $coursevideos[$identifier] = self::render_course_video_menu_entry($video);
        }

        // Finally, return the list of course videos.
        return $coursevideos;
    }

    /**
     * Helperfunction to get the list of course videos which are stored in the given source course to be shown as summary.
     *
     * @param int $sourcecourseid The source course id.
     * @param array $selectedcoursevideos The selected course videos.
     *
     * @return array
     */
    public static function get_import_source_course_videos_summary($sourcecourseid, $selectedcoursevideos) {
        // Initialize course videos summary as empty array;
        $coursevideossummary = array();

        // If the user is not allowed to import from the given course at all, return.
        $coursecontext = \context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $coursecontext) != true) {
            return $coursevideossummary;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Get course videos which are qualified to be imported.
        $allcoursevideos = $apibridge->get_course_videos_for_backup($sourcecourseid);

        // If there aren't any videos, return.
        if (count($allcoursevideos) < 1) {
            return $coursevideossummary;
        }

        // Iterate over all course videos which have been selected to be imported.
        foreach ($selectedcoursevideos as $identifier => $checked) {
            // If the event does not exist anymore in Opencast in the meantime, skip it silently.
            if ($apibridge->get_already_existing_event(array($identifier)) == false) {
                continue;
            }

            // If, for any reason even though it should have been sorted out before, a video is marked as not checked,
            // skip it silently.
            if ($checked != 1) {
                continue;
            }

            // Add the selected video to the course video summary.
            // During this step, render the course video menu entry with all necessary information.
            $coursevideossummary[$identifier] = self::render_course_video_menu_entry($allcoursevideos[$identifier]);

        }

        // Finally, return the list of course videos.
        return $coursevideossummary;
    }

    /**
     * Helperfunction to duplicate the videos which have been selected during manual import.
     *
     * @param int $sourcecourseid The source course id.
     * @param int $targetcourseid The target course id.
     * @param array $coursevideos The array of video identifiers to be duplicated.
     *
     * @return bool
     */
    public static function duplicate_videos($sourcecourseid, $targetcourseid, $coursevideos) {
        // If the user is not allowed to import from the source course at all, return.
        $sourcecoursecontext = \context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $sourcecoursecontext) != true) {
            return false;
        }

        // If the user is not allowed to import to the target course at all, return.
        $targetcoursecontext = \context_course::instance($targetcourseid);
        if (has_capability('block/opencast:manualimporttarget', $targetcoursecontext) != true) {
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Get source course series ID.
        $sourceseriesid = $apibridge->get_stored_seriesid($sourcecourseid);

        // Get target course series ID, create a new one if necessary.
        $targetseriesid = $apibridge->get_stored_seriesid($targetcourseid, true);

        // Process the array of course videos.
        foreach ($coursevideos as $identifier => $checked) {
            // Get the existing event from Opencast.
            $event = $apibridge->get_already_existing_event(array($identifier));

            // If the event does not exist anymore in Opencast in the meantime, skip it silently.
            if (!$event) {
                continue;
            }

            // If the given course video does not belong to the source course series, skip it to prevent any fraud silently.
            if ($event->is_part_of != $sourceseriesid) {
                continue;
            }

            // If, for any reason even though it should have been sorted out before, a video is marked as not checked,
            // skip it silently.
            if ($checked != 1) {
                continue;
            }

            // Create duplication task for this event.
            $ret = \block_opencast\local\event::create_duplication_task($targetcourseid, $targetseriesid, $identifier);

            // If there was any problem with creating this task.
            if ($ret == false) {
                // We seem to have a problem and should not continue to fire requests into a broken system.
                // Stop here and inform the caller.
                return false;
            }
        }

        // Finally, inform the caller that the tasks have been created.
        return true;
    }

    /**
     * Helperfunction to render the HTML code of a course video menu entry.
     *
     * @param Object $video
     *
     * @return string
     */
    public static function render_course_video_menu_entry($video) {
        // Add the video title.
        $entrystring = $video->title;

        // Add the start date, if set.
        if (!empty($video->start)) {
            $entrystring .= \html_writer::empty_tag('br');
            $entrystring .= \html_writer::start_tag('small');
            $entrystring .= get_string('startDate', 'block_opencast').': ';
            $entrystring .= userdate(strtotime($video->start), get_string('strftimedatetime', 'langconfig'));
            $entrystring .= \html_writer::end_tag('small');
        }

        // Add the presenter(s), if set.
        if (count($video->presenter) > 0) {
            $entrystring .= \html_writer::empty_tag('br');
            $entrystring .= \html_writer::start_tag('small');
            $entrystring .= get_string('creator', 'block_opencast').': ';
            $entrystring .= implode(', ', $video->presenter);
            $entrystring .= \html_writer::end_tag('small');
        }

        // Finally, return the menu entry code.
        return $entrystring;
    }

    /**
     * Helperfunction to render the HTML code of the progress bar.
     *
     * @param int $currentstep
     * @param int $maxsteps
     * @param bool $hasstep3
     *
     * @return string
     */
    public static function render_progress_bar($currentstep = 1, $maxsteps = 4, $hasstep3 = true) {
        // If we don't have step 3, we have to respect that
        if ($hasstep3 == false) {
            // The whole progress bar has one step less.
            $maxsteps -= 1;
            // After step 3, the current step is one step less.
            if ($currentstep > 3) {
                $currentstep -= 1;
            }
        }

        // Compose progress bar (based on Bootstrap).
        $progressbar = \html_writer::start_div('progress my-3');
        $progressbar .= \html_writer::start_div('progress-bar',
                array('role' => 'progressbar',
                      'style' => 'width: '.(floor(($currentstep / $maxsteps)*100)).'%',
                      'aria-valuenow' => $currentstep,
                      'aria-valuemin' => '0',
                      'aria-valuemax' => $maxsteps));
        $progressbar .= \html_writer::start_span('text-left pl-2');
        $progressbar .= get_string('importvideos_progressbarstep', 'block_opencast', array('current' => $currentstep, 'last' => $maxsteps));
        $progressbar .= \html_writer::end_span('');
        $progressbar .= \html_writer::end_div();
        $progressbar .= \html_writer::end_div();

        // Finally, return the progress bar code.
        return $progressbar;
    }

    /**
     * Helperfunction to render an intro notification for the wizard.
     *
     * @param string $intromessage
     *
     * @return string
     */
    public static function render_wizard_intro_notification($intromessage) {
        // Compose notification.
        $notification = \html_writer::start_div('alert alert-info');
        $notification .= $intromessage;
        $notification .= \html_writer::end_div();

        // Finally, return the intro notification code.
        return $notification;
    }

    /**
     * Helperfunction to render an error notification for the wizard.
     *
     * @param string $errormessage
     *
     * @return string
     */
    public static function render_wizard_error_notification($errormessage) {
        // Compose notification.
        $notification = \html_writer::start_div('alert alert-danger');
        $notification .= $errormessage;
        $notification .= \html_writer::end_div();

        // Finally, return the error notification code.
        return $notification;
    }
}
