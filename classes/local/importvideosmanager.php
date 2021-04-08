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

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

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

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

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
     * Helperfunction to get the status of the handle Opencast series modules feature.
     *
     * @return boolean
     */
    public static function handle_series_modules_is_enabled_and_working() {
        // Get the status of the feature.
        $config = get_config('block_opencast', 'importvideoshandleseriesenabled');

        // If the setting is false, then the feature is not working.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

        // Get the status of the LTI module feature (which is the basis for this feature).
        $basisfeature = \block_opencast\local\ltimodulemanager::is_enabled_and_working_for_series();

        // If the LTI module is not working, then this feature is not working as well.
        if ($basisfeature == false) {
            // Inform the caller.
            return false;
        }

        // The feature is working.
        return true;
    }

    /**
     * Helperfunction to get the status of the handle Opencast episode modules feature.
     *
     * @return boolean
     */
    public static function handle_episode_modules_is_enabled_and_working() {
        // Get the status of the feature.
        $config = get_config('block_opencast', 'importvideoshandleepisodeenabled');

        // If the setting is false, then the feature is not working.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

        // Get the status of the LTI module feature (which is the basis for this feature).
        $basisfeature = \block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes();

        // If the LTI module is not working, then this feature is not working as well.
        if ($basisfeature == false) {
            // Inform the caller.
            return false;
        }

        // Check the support of Opencast API Level >=v1.1.0.
        $apibridge = \block_opencast\local\apibridge::get_instance();
        $apilevelsupported = $apibridge->supports_api_level('v1.1.0');

        // If the API level is too old, then the feature is not working.
        if ($apilevelsupported == false) {
            // Inform the caller.
            return false;
        }

        // The feature is working.
        return true;
    }

    /**
     * Helperfunction to get the list of course videos which are stored in the given source course to be selected during manual import.
     *
     * @param int $sourcecourseid The source course id.
     *
     * @return array
     */
    public static function get_import_source_course_videos_menu($sourcecourseid) {
        global $PAGE;

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

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
            $coursevideos[$identifier] = $renderer->course_video_menu_entry($video);
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
        global $PAGE;

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

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
            $coursevideossummary[$identifier] = $renderer->course_video_menu_entry($allcoursevideos[$identifier]);

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
     * @param bool $modulecleanup (optional) The switch if we want to cleanup the episode modules.
     *
     * @return bool
     */
    public static function duplicate_videos($sourcecourseid, $targetcourseid, $coursevideos, $modulecleanup = false) {
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

            // If cleanup of the episode modules was requested, look for existing modules.
            if ($modulecleanup == true) {
                // Get the episode modules to be cleaned up.
                $episodemodules = \block_opencast\local\ltimodulemanager::get_modules_for_episode_linking_to_other_course(
                        $targetcourseid, $identifier);
            }

            // If there are existing modules to be cleaned up.
            if ($modulecleanup == true && count($episodemodules) > 0) {
                // Create duplication task for this event.
                $ret = \block_opencast\local\event::create_duplication_task($targetcourseid, $targetseriesid, $identifier,
                        true, $episodemodules);
            } else {
                // Create duplication task for this event.
                $ret = \block_opencast\local\event::create_duplication_task($targetcourseid, $targetseriesid, $identifier,
                        false, null);
            }

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
}
