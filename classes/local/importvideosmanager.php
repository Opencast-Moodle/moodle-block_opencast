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

use context_course;

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
    public static function is_enabled_and_working_for_manualimport($ocinstanceid) {
        // Get the status of the whole import featureset.
        $config = get_config('block_opencast', 'importvideosenabled_' . $ocinstanceid);

        // If the setting is false, then the featureset is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

        // Get the status of the manual import setting.
        // Since this setting is shared between both import modes, we put it before import mode checkers.
        $config = get_config('block_opencast', 'importvideosmanualenabled_' . $ocinstanceid);

        // If the setting is false, then the subfeature is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the import mode.
        $importmode = get_config('block_opencast', 'importmode_' . $ocinstanceid);

        if (empty($importmode)) {
            // Inform the caller.
            return false;
        }

        // If Duplicating Events is selected as the import mode.
        if ($importmode == 'duplication') {
            // Get the configured duplicate workflow.
            $workflow = get_config('block_opencast', 'duplicateworkflow_' . $ocinstanceid);

            // If the workflow is empty, then the feature is not working.
            if (empty($workflow)) {
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
    public static function is_enabled_and_working_for_coreimport($ocinstanceid) {
        // Get the status of the whole import featureset.
        $config = get_config('block_opencast', 'importvideosenabled_' . $ocinstanceid);

        // If the setting is false, then the featureset is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the status of the subfeature.
        $config = get_config('block_opencast', 'importvideoscoreenabled_' . $ocinstanceid);

        // If the setting is false, then the subfeature is not enabled.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the import mode.
        $importmode = get_config('block_opencast', 'importmode_' . $ocinstanceid);

        if (empty($importmode)) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

        // If Duplicating Events is selected as the import mode.
        if ($importmode == 'duplication') {
            // Get the configured duplicate workflow.
            $workflow = get_config('block_opencast', 'duplicateworkflow_' . $ocinstanceid);

            // If the workflow is empty, then the feature is not working.
            if (empty($workflow)) {
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
        }

        // The feature should be working.
        return true;
    }

    /**
     * Helperfunction to get the status of the handle Opencast series modules feature.
     *
     * @return boolean
     */
    public static function handle_series_modules_is_enabled_and_working($ocinstanceid) {
        // Get the status of the feature.
        $config = get_config('block_opencast', 'importvideoshandleseriesenabled_' . $ocinstanceid);

        // If the setting is false, then the feature is not working.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

        return true;
    }

    /**
     * Helperfunction to get the status of the handle Opencast episode modules feature.
     *
     * @return boolean
     */
    public static function handle_episode_modules_is_enabled_and_working($ocinstanceid) {
        // Get the status of the feature.
        $config = get_config('block_opencast', 'importvideoshandleepisodeenabled_' . $ocinstanceid);

        // If the setting is false, then the feature is not working.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get the APIbridge configuration status.
        $apibridgeworking = $apibridge->check_api_configuration();

        // If the status is false, then the feature is not working.
        if (!$apibridgeworking) {
            // Inform the caller.
            return false;
        }

        return true;
    }

    /**
     * Helperfunction to get the list of course videos that are stored in the
     * given source course to be selected during manual import.
     *
     * @param int $sourcecourseid The source course id.
     *
     * @return array
     */
    public static function get_import_source_course_series_and_videos_menu($ocinstanceid, $sourcecourseid) {
        global $PAGE;

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Initialize course videos as empty array.
        $courseseries = array();

        // If the user is not allowed to import from the given course at all, return.
        $coursecontext = \context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $coursecontext) != true) {
            return $courseseries;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get course series with videos which are qualified to be imported.
        $coursebackupseries = $apibridge->get_course_series_and_videos_for_backup($sourcecourseid);

        // If there aren't any videos, return.
        if (count($coursebackupseries) < 1) {
            return $courseseries;
        }

        // Move the course series from the array of objects into the course series array to be used by the caller.
        // During this movement, render the course video menu entry with all necessary information.
        foreach ($coursebackupseries as $identifier => $videos) {
            $title = $apibridge->get_series_by_identifier($identifier)->title;
            if (!$title) {
                $title = $identifier;
            }
            $courseseries[$identifier] = array('title' => $title, 'videos' => array());

            foreach ($videos as $videoid => $video) {
                $courseseries[$identifier]['videos'][$videoid] = $renderer->course_video_menu_entry($video);
            }
        }

        // Finally, return the list of course videos.
        return $courseseries;
    }

    /**
     * Helperfunction to get the list of course videos which are stored in the given source course to be shown as summary.
     *
     * @param int $sourcecourseid The source course id.
     * @param array $selectedcoursevideos The selected course videos.
     *
     * @return array
     */
    public static function get_import_source_course_videos_summary($ocinstanceid, $sourcecourseid, $selectedcoursevideos) {
        global $PAGE;

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Initialize course videos summary as empty array.
        $coursevideossummary = array();

        // If the user is not allowed to import from the given course at all, return.
        $coursecontext = \context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $coursecontext) != true) {
            return $coursevideossummary;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Iterate over all course videos which have been selected to be imported.
        foreach ($selectedcoursevideos as $identifier => $checked) {
            // If the event does not exist anymore in Opencast in the meantime, skip it silently.
            $video = $apibridge->get_already_existing_event(array($identifier));
            if ($video == false) {
                continue;
            }

            // If, for any reason even though it should have been sorted out before, a video is marked as not checked,
            // skip it silently.
            if ($checked != 1) {
                continue;
            }

            // Add the selected video to the course video summary.
            // During this step, render the course video menu entry with all necessary information.
            if (!array_key_exists($video->is_part_of, $coursevideossummary)) {
                $title = $video->series;
                if (!$title) {
                    $title = $video->is_part_of;
                }
                $coursevideossummary[$video->is_part_of] = array('title' => $title, 'videos' => array());
            }
            $coursevideossummary[$video->is_part_of]['videos'][$identifier] = $renderer->course_video_menu_entry($video);

        }

        // Finally, return the list of course videos.
        return $coursevideossummary;
    }

    public static function get_import_source_course_series($ocinstanceid, $sourcecourseid) {
        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);
        $series = $apibridge->get_course_series($sourcecourseid);
        $serieswithnames = array();

        foreach ($series as $s) {
            $ocseries = $apibridge->get_series_by_identifier($s->series);
            $serieswithnames[$s->series] = $ocseries->title;
        }

        return $serieswithnames;
    }

    /**
     * Helperfunction to duplicate the videos which have been selected during manual import.
     *
     * @param int $sourcecourseid The source course id.
     * @param int $targetcourseid The target course id.
     * @param array $coursevideos The array of video identifiers to be duplicated.
     * @param bool $modulecleanup (optional) The switch if we want to cleanup the episode modules.
     *
     * @return \stdClass
     */
    public static function duplicate_videos($ocinstanceid, $sourcecourseid, $targetcourseid,
                                            $coursevideos, $modulecleanup = false) {
        global $USER;
        $result = new \stdClass();

        // If the user is not allowed to import from the source course at all, return.
        $sourcecoursecontext = \context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $sourcecoursecontext) != true) {
            $result->error = true;
            return $result;
        }

        // If the user is not allowed to import to the target course at all, return.
        $targetcoursecontext = \context_course::instance($targetcourseid);
        if (has_capability('block/opencast:manualimporttarget', $targetcoursecontext) != true) {
            $result->error = true;
            return $result;
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get source course series ID.
        $sourceseries = array_column($apibridge->get_course_series($sourcecourseid), 'series');

        // Get target course series ID, create a new one if necessary.
        $targetseriesid = $apibridge->get_stored_seriesid($targetcourseid, true, $USER->id);

        $duplicatedsourceseries = array();

        // Process the array of course videos.
        foreach ($coursevideos as $identifier => $checked) {
            // Get the existing event from Opencast.
            $event = $apibridge->get_already_existing_event(array($identifier));

            // If the event does not exist anymore in Opencast in the meantime, skip it silently.
            if (!$event) {
                continue;
            }

            // If the given course video does not belong to the source course series, skip it to prevent any fraud silently.
            if (!in_array($event->is_part_of, $sourceseries)) {
                continue;
            }

            // If, for any reason even though it should have been sorted out before, a video is marked as not checked,
            // skip it silently.
            if ($checked != 1) {
                continue;
            }

            $duplicatedsourceseries[] = $event->is_part_of;

            // If cleanup of the episode modules was requested, look for existing modules.
            if ($modulecleanup == true) {
                // Get the episode modules to be cleaned up.
                $episodemodules = array();

                // For LTI check if capability is fulfilled.
                if (\block_opencast\local\ltimodulemanager::is_working_for_episodes($ocinstanceid) &&
                    has_capability('block/opencast:addltiepisode', context_course::instance($targetcourseid))) {
                    $episodemodules = ltimodulemanager::get_modules_for_episode_linking_to_other_course(
                        $ocinstanceid, $targetcourseid, $identifier);
                }

                if (\core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null) {
                    $episodemodules += activitymodulemanager::get_modules_for_episode_linking_to_other_course(
                        $ocinstanceid, $targetcourseid, $identifier);
                }
            }

            // If there are existing modules to be cleaned up.
            if ($modulecleanup == true && count($episodemodules) > 0) {
                // Create duplication task for this event.
                $ret = event::create_duplication_task($ocinstanceid, $targetcourseid, $targetseriesid, $identifier,
                    true, $episodemodules);
            } else {
                // Create duplication task for this event.
                $ret = event::create_duplication_task($ocinstanceid, $targetcourseid, $targetseriesid, $identifier,
                    false, null);
            }

            // If there was any problem with creating this task.
            if ($ret == false) {
                // We seem to have a problem and should not continue to fire requests into a broken system.
                // Stop here and inform the caller.
                $result->error = true;
                return $result;
            }
        }

        // Finally, inform the caller that the tasks have been created.
        $result->error = false;
        $result->duplicatedseries = array_unique($duplicatedsourceseries);
        return $result;
    }

    /**
     * Helperfunction to get the list of course videos which are stored in the given source course
     * to be shown as summary in ACL change mode.
     *
     * @param int $sourcecourseid The source course id.
     *
     * @return string
     */
    public static function get_import_acl_source_series_videos_summary($ocinstanceid, $seriesid) {
        global $PAGE;

        // Get renderers.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get course videos which are qualified to be imported.
        $result = $apibridge->get_series_videos($seriesid);

        $videos = [];

        if ($result && $result->error == 0) {
            foreach ($result->videos as $video) {
                $videos[$video->identifier] = $video;
            }
        }

        // If there aren't any videos, return only a warning message.
        if (count($videos) < 1) {
            return $renderer->wizard_warning_notification(
                get_string('importvideos_wizardstep2coursevideosnone', 'block_opencast'));
        }

        // Initialize course videos entry strings as empty array.
        $coursevideosenteries = [];

        foreach ($videos as $identifier => $video) {
            // Get video entry string one by one.
            $coursevideosenteries[] = $renderer->course_video_menu_entry($video);
        }

        // Render the list of enteries as an unordered list.
        $coursevideossummary = $renderer->course_videos_list_entry($coursevideosenteries);

        // Finally, return the string of course videos summary.
        return $coursevideossummary;
    }

    /**
     * Helperfunction to perform ACL Change approache during manual import.
     *
     * @param int $sourcecourseid The source course id.
     * @param int $targetcourseid The target course id.
     *
     * @return object
     */
    public static function change_acl($ocinstanceid, $sourcecourseid, $sourcecourseseries, $targetcourseid) {
        global $USER, $PAGE;

        // Initialize the result as empty object to handle it later on.
        $aclchangeresult = new \stdClass();

        // Assuming that everything goes fine, we define the return result variable.
        $aclchangeresult->message = get_string('importvideos_importjobaclchangedone', 'block_opencast');
        $aclchangeresult->type = \core\output\notification::NOTIFY_SUCCESS;

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Import series and course videos into the targeted course.
        $result = $apibridge->import_series_to_course_with_acl_change($targetcourseid, $sourcecourseseries, $USER->id);

        // We decide what to show as a return message based on errors.
        if ($result->error == 1) {
            // There are 3 different outcomes, we prioritize the importance of errors here.
            if (!$result->seriesaclchange) {
                // 1. When there is error with changing Series ACL.
                $aclchangeresult->message = get_string('importvideos_importjobaclchangeseriesfailed', 'block_opencast');
                $aclchangeresult->type = \core\output\notification::NOTIFY_ERROR;
            } else if (!$result->seriesmapped) {
                // 2. When there is error with mapping series to the course correctly.
                // It is very unlikely to happen but it is important if it does.
                $aclchangeresult->message = get_string('importvideos_importjobaclchangeseriesmappingfailed', 'block_opencast');
                $aclchangeresult->type = \core\output\notification::NOTIFY_ERROR;
            } else if (count($result->eventsaclchange->failed) > 1) {
                // 3. Error might happen during events ACL changes, but it should not be as important as other errors,
                // only a notification would be enough.
                // If all videos have failed, we notify user as a warning.
                if (count($result->eventsaclchange->failed) == $result->eventsaclchange->total) {
                    $aclchangeresult->message = get_string('importvideos_importjobaclchangealleventsfailed', 'block_opencast');
                    $aclchangeresult->type = \core\output\notification::NOTIFY_WARNING;
                } else {
                    // But if some of them have failed, we notify user as warning with the id of the events.
                    // Get renderer.
                    $renderer = $PAGE->get_renderer('block_opencast');
                    $aclchangeresult->message = get_string('importvideos_importjobaclchangeeventsfailed', 'block_opencast');
                    $aclchangeresult->message .= $renderer->render_list($result->eventsaclchange->failed);
                    $aclchangeresult->type = \core\output\notification::NOTIFY_WARNING;
                }
            }
        }

        // Finall, we return the object containing the information about all 3 steps in this method.
        return $aclchangeresult;
    }
}
