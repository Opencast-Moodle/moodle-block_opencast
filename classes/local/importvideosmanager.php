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
use core\output\notification;
use core_plugin_manager;
use dml_exception;
use stdClass;

/**
 * Import videos management for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importvideosmanager {


    /** @var int course wizard restore status is started */
    const RESTORE_STATUS_STARTED = 0;

    /** @var int course wizard restore status is completed */
    const RESTORE_STATUS_COMPLETED = 1;

    /** @var int Episode import mapping type */
    const MAPPING_TYPE_EPISODE = 1;

    /** @var int Series import mapping type */
    const MAPPING_TYPE_SERIES = 2;

    /** @var int Import mapping status success */
    const MAPPING_STATUS_SUCCESS = 1;

    /** @var int Import mapping status pending */
    const MAPPING_STATUS_PENDING = 0;

    /** @var int Import mapping status failed */
    const MAPPING_STATUS_FAILED = 2;

    /**
     * Helperfunction to get the status of the manual import videos feature.
     * This consists of a check if the feature is enabled by the admin and a check if a duplicate workflow is configured.
     *
     * @param int $ocinstanceid Opencast instance id.
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
        $apibridge = apibridge::get_instance($ocinstanceid);

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
     * @param int $ocinstanceid Opencast instance id.
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
        $apibridge = apibridge::get_instance($ocinstanceid);

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
     * @param int $ocinstanceid Opencast instance id.
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
        $apibridge = apibridge::get_instance($ocinstanceid);

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
     * @param int $ocinstanceid Opencast instance id.
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
        $apibridge = apibridge::get_instance($ocinstanceid);

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
     * @param int $ocinstanceid Opencast instance id.
     * @param int $sourcecourseid The source course id.
     *
     * @return array
     */
    public static function get_import_source_course_series_and_videos_menu($ocinstanceid, $sourcecourseid) {
        global $PAGE;

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Initialize course videos as empty array.
        $courseseries = [];

        // If the user is not allowed to import from the given course at all, return.
        $coursecontext = context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $coursecontext) != true) {
            return $courseseries;
        }

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

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
            $courseseries[$identifier] = ['title' => $title, 'videos' => []];

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
     * @param int $ocinstanceid Opencast instance id.
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
        $coursevideossummary = [];

        // If the user is not allowed to import from the given course at all, return.
        $coursecontext = context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $coursecontext) != true) {
            return $coursevideossummary;
        }

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Iterate over all course videos which have been selected to be imported.
        foreach ($selectedcoursevideos as $identifier => $checked) {
            // If the event does not exist anymore in Opencast in the meantime, skip it silently.
            $video = $apibridge->get_already_existing_event([$identifier]);
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
                $coursevideossummary[$video->is_part_of] = ['title' => $title, 'videos' => []];
            }
            $coursevideossummary[$video->is_part_of]['videos'][$identifier] = $renderer->course_video_menu_entry($video);

        }

        // Finally, return the list of course videos.
        return $coursevideossummary;
    }

    /**
     * Get all series from the source course.
     * @param int $ocinstanceid Opencast instance id.
     * @param int $sourcecourseid
     * @return array
     * @throws dml_exception
     */
    public static function get_import_source_course_series($ocinstanceid, $sourcecourseid) {
        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);
        $series = $apibridge->get_course_series($sourcecourseid);
        $serieswithnames = [];

        foreach ($series as $s) {
            $ocseries = $apibridge->get_series_by_identifier($s->series);
            $serieswithnames[$s->series] = $ocseries->title;
        }

        return $serieswithnames;
    }

    /**
     * Helperfunction to duplicate the videos which have been selected during manual import.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $sourcecourseid The source course id.
     * @param int $targetcourseid The target course id.
     * @param array $coursevideos The array of video identifiers to be duplicated.
     * @param bool $modulecleanup (optional) The switch if we want to cleanup the episode modules.
     *
     * @return stdClass
     */
    public static function duplicate_videos($ocinstanceid, $sourcecourseid, $targetcourseid,
                                            $coursevideos, $modulecleanup = false) {
        global $USER;
        $result = new stdClass();

        // If the user is not allowed to import from the source course at all, return.
        $sourcecoursecontext = context_course::instance($sourcecourseid);
        if (has_capability('block/opencast:manualimportsource', $sourcecoursecontext) != true) {
            $result->error = true;
            return $result;
        }

        // If the user is not allowed to import to the target course at all, return.
        $targetcoursecontext = context_course::instance($targetcourseid);
        if (has_capability('block/opencast:manualimporttarget', $targetcoursecontext) != true) {
            $result->error = true;
            return $result;
        }

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get source course series ID.
        $sourceseries = array_column($apibridge->get_course_series($sourcecourseid), 'series');

        // Get target course series ID, create a new one if necessary.
        $targetseriesid = $apibridge->get_stored_seriesid($targetcourseid, true, $USER->id);

        $duplicatedsourceseries = [];

        // Process the array of course videos.
        foreach ($coursevideos as $identifier => $checked) {
            // Get the existing event from Opencast.
            $event = $apibridge->get_already_existing_event([$identifier]);

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
                $episodemodules = [];

                // For LTI check if capability is fulfilled.
                if (ltimodulemanager::is_working_for_episodes($ocinstanceid) &&
                    has_capability('block/opencast:addltiepisode', context_course::instance($targetcourseid))) {
                    $episodemodules = ltimodulemanager::get_modules_for_episode_linking_to_other_course(
                        $ocinstanceid, $targetcourseid, $identifier);
                }

                if (core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null) {
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
     * @param int $ocinstanceid Opencast instance id.
     * @param int $seriesid The source series id.
     *
     * @return string
     */
    public static function get_import_acl_source_series_videos_summary($ocinstanceid, $seriesid) {
        global $PAGE;

        // Get renderers.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

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
     * @param int $ocinstanceid Opencast instance id.
     * @param int $sourcecourseid The source course id.
     * @param string $sourcecourseseries
     * @param int $targetcourseid The target course id.
     *
     * @return object
     */
    public static function change_acl($ocinstanceid, $sourcecourseid, $sourcecourseseries, $targetcourseid) {
        global $USER, $PAGE;

        // Initialize the result as empty object to handle it later on.
        $aclchangeresult = new stdClass();

        // Assuming that everything goes fine, we define the return result variable.
        $aclchangeresult->message = get_string('importvideos_importjobaclchangedone', 'block_opencast');
        $aclchangeresult->type = notification::NOTIFY_SUCCESS;

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Import series and course videos into the targeted course.
        $result = $apibridge->import_series_to_course_with_acl_change($targetcourseid, $sourcecourseseries, $USER->id);

        // We decide what to show as a return message based on errors.
        if ($result->error == 1) {
            // There are 3 different outcomes, we prioritize the importance of errors here.
            if (!$result->seriesaclchange) {
                // 1. When there is error with changing Series ACL.
                $aclchangeresult->message = get_string('importvideos_importjobaclchangeseriesfailed', 'block_opencast');
                $aclchangeresult->type = notification::NOTIFY_ERROR;
            } else if (!$result->seriesmapped) {
                // 2. When there is error with mapping series to the course correctly.
                // It is very unlikely to happen but it is important if it does.
                $aclchangeresult->message = get_string('importvideos_importjobaclchangeseriesmappingfailed', 'block_opencast');
                $aclchangeresult->type = notification::NOTIFY_ERROR;
            } else if (count($result->eventsaclchange->failed) > 1) {
                // 3. Error might happen during events ACL changes, but it should not be as important as other errors,
                // only a notification would be enough.
                // If all videos have failed, we notify user as a warning.
                if (count($result->eventsaclchange->failed) == $result->eventsaclchange->total) {
                    $aclchangeresult->message = get_string('importvideos_importjobaclchangealleventsfailed', 'block_opencast');
                    $aclchangeresult->type = notification::NOTIFY_WARNING;
                } else {
                    // But if some of them have failed, we notify user as warning with the id of the events.
                    // Get renderer.
                    $renderer = $PAGE->get_renderer('block_opencast');
                    $aclchangeresult->message = get_string('importvideos_importjobaclchangeeventsfailed', 'block_opencast');
                    $aclchangeresult->message .= $renderer->render_list($result->eventsaclchange->failed);
                    $aclchangeresult->type = notification::NOTIFY_WARNING;
                }
            }
        }

        // Finall, we return the object containing the information about all 3 steps in this method.
        return $aclchangeresult;
    }

    /**
     * Gets the import mapping records of series and perform the lookup and fix.
     * It also cleans up after the attemp is completed.
     * @uses ltimodulemanager::fix_imported_series_modules_in_new_course() to lookup and fix series LTI modules.
     * @uses activitymodulemanager::fix_imported_series_modules_in_new_course() to lookup and fix series Activity modules.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid Course id.
     * @param string $newseriesid The newly created series id in the new course.
     * @param string $restoreuid The restore unique id.
     *
     * @return void
     */
    public static function fix_imported_series_modules_in_new_course($ocinstanceid, $courseid, $newseriesid, $restoreuid) {

        $seriesimportmapping = self::get_import_mapping_records([
            'restoreuid' => $restoreuid,
            'ocinstanceid' => $ocinstanceid,
            'type' => self::MAPPING_TYPE_SERIES,
            'targetcourseid' => $courseid,
        ]);

        foreach ($seriesimportmapping as $mapping) {
            // LTI Modules.
            ltimodulemanager::fix_imported_series_modules_in_new_course(
                $ocinstanceid, $courseid, $mapping->sourceseriesid, $newseriesid
            );

            // Activity modules.
            activitymodulemanager::fix_imported_series_modules_in_new_course(
                $ocinstanceid, $courseid, $mapping->sourceseriesid, $newseriesid
            );

            // At this point, we have no use for the series import mapping record anymore,
            // and we remove it from db.
            self::delete_import_mapping_record(['id' => $mapping->id]);
        }
    }

    /**
     * Performs the look up and fix for the episode LTI & Activity modules in the newly imported course.
     * @uses ltimodulemanager::fix_imported_episode_modules_in_new_course() to lookup and fix episode LTI modules.
     * @uses activitymodulemanager::fix_imported_episode_modules_in_new_course() to lookup and fix episode Activity modules.
     *
     * @param stdClass $mapping The import mapping object.
     * @param string $duplicatedeventid TThe duplicated event id.
     *
     * @return void
     */
    public static function fix_imported_episode_modules_in_new_course($mapping, $duplicatedeventid) {
        // LTI modules in the new course.
        ltimodulemanager::fix_imported_episode_modules_in_new_course(
            $mapping->ocinstanceid,
            $mapping->targetcourseid,
            $mapping->sourceeventid,
            $duplicatedeventid
        );

        // Activity Modules in the new course.
        activitymodulemanager::fix_imported_episode_modules_in_new_course(
            $mapping->ocinstanceid,
            $mapping->targetcourseid,
            $mapping->sourceeventid,
            $duplicatedeventid
        );
    }

    /**
     * Saves the series import mapping record.
     *
     * @param int $ocinstanceid The OC instance id.
     * @param int $targetcourseid The new course id.
     * @param string $sourceseriesid The source series id.
     * @param string $restoreuniqueid The unique id of the restore session.
     *
     * @return bool Whether the mapping record is inserted into db or not.
     */
    public static function save_series_import_mapping_record($ocinstanceid, $targetcourseid,
        $sourceseriesid, $restoreuniqueid) {
        $mappingobj = new \stdClass();
        $mappingobj->type = self::MAPPING_TYPE_SERIES;
        $mappingobj->restoreuid  = $restoreuniqueid;
        $mappingobj->ocinstanceid = $ocinstanceid;
        $mappingobj->targetcourseid = $targetcourseid;
        $mappingobj->sourceseriesid = $sourceseriesid;
        return self::save_import_mapping_record($mappingobj);
    }

    /**
     * Saves the episode import mapping record.
     *
     * @param int $ocinstanceid The OC instance id.
     * @param int $targetcourseid The new course id.
     * @param string $sourceeventid The source event id.
     * @param string $restoreuniqueid The unique id of the restore session.
     *
     * @return bool Whether the mapping record is inserted into db or not.
     */
    public static function save_episode_import_mapping_record($ocinstanceid, $targetcourseid,
        $sourceeventid, $restoreuniqueid) {
        $mappingobj = new \stdClass();
        $mappingobj->type = self::MAPPING_TYPE_EPISODE;
        $mappingobj->restoreuid  = $restoreuniqueid;
        $mappingobj->ocinstanceid = $ocinstanceid;
        $mappingobj->targetcourseid = $targetcourseid;
        $mappingobj->sourceeventid = $sourceeventid;
        return self::save_import_mapping_record($mappingobj);
    }

    /**
     * Inserts a mapping record into db. It also validates the mapping object.
     *
     * @param stdClass $mapping the mapping object.
     *
     * @return bool whether the mapping record is inserted successfully.
     */
    public static function save_import_mapping_record($mapping) {
        global $DB;
        // Set the started status.
        $mapping->restorecompleted = self::RESTORE_STATUS_STARTED;
        $mapping->status = self::MAPPING_STATUS_PENDING;
        $mapping->timecreated = time();
        if (!self::validate_mapping_record($mapping)) {
            return false;
        }
        // Save into db.
        return $DB->insert_record('block_opencast_importmapping', $mapping);
    }

    /**
     * Updates the improt mapping record. It perform a validation before updating as well.
     *
     * @param stdClass $mapping The import mapping record object.
     *
     * @return boolean Whether the update was valid and successful.
     */
    public static function update_import_mapping_record($mapping) {
        global $DB;
        $mapping->timemodified = time();
        if (!self::validate_mapping_record($mapping)) {
            return false;
        }
        // Update the record in db.
        return $DB->update_record('block_opencast_importmapping', $mapping);
    }

    /**
     * Finds and returns the import mapping record based on a where clause.
     *
     * @param array $where The array of where clauses to look for.
     *
     * @return stdClass|null The import mapping record object or null if not found.
     */
    public static function get_import_mapping_record($where) {
        global $DB;
        if ($DB->record_exists('block_opencast_importmapping', $where)) {
            return $DB->get_record('block_opencast_importmapping', $where);
        }
        return null;
    }

    /**
     * Finds and returns the list import mapping records based on a where clause.
     *
     * @param array $where The array of where clauses to look for.
     *
     * @return array An array of import mapping record objects.
     */
    public static function get_import_mapping_records($where) {
        global $DB;
        $records = $DB->get_records('block_opencast_importmapping', $where);
        return $records ?? [];
    }

    /**
     * Deletes an import mapping record.
     *
     * @param array $where the array of where clause.
     *
     * @return bool whether the deletion was successful
     */
    public static function delete_import_mapping_record($where) {
        global $DB;
        if ($DB->record_exists('block_opencast_importmapping', $where)) {
            return $DB->delete_records('block_opencast_importmapping', $where);
        }
        return false;
    }

    /**
     * Sets the completion status of a restore session.
     * @used-by restore_opencast_block_structure_step::after_restore() to make sure the restore session is completed
     *  and we can proceed with the after import module fixes
     *
     * @param string $restoreuid the restore session unique identifier
     * @param int $status the status id
     *
     * @return bool|array true if the status of all records under restore unique id have been changed,
     *  array mapping ids of unchanged otherwise.
     */
    public static function set_import_mapping_completion_status($restoreuid, $status = self::RESTORE_STATUS_COMPLETED) {
        global $DB;
        $records = $DB->get_records('block_opencast_importmapping', ['restoreuid' => $restoreuid]);
        $unchanged = [];
        foreach ($records as $record) {
            $record->restorecompleted = $status;
            $record->timemodified = time();
            $success = $DB->update_record('block_opencast_importmapping', $record, true);
            // We track the success of changes to report back,
            // however series are not neccessary and we escape them,
            // because they are waiting for the restore session to be completed.
            if (!$success && $record->type == self::MAPPING_TYPE_EPISODE) {
                $unchanged[] = $record->sourceeventid ?? 'Mapping id: ' . $record->id;
            }
        }
        return empty($unchanged) ? true : $unchanged;
    }

    /**
     * Finds the mapping record of the event and sets the ocworkflowid
     *
     * @param array $where the array of where clause.
     * @param int $ocworkflowid the id of duplicating workflow that holds the new event id later on.
     *
     * @return int|false the id of the mapping record of successful, false otherwise.
     */
    public static function set_import_mapping_workflowid($where, $ocworkflowid) {
        $mapping = self::get_import_mapping_record($where);
        if (!empty($mapping)) {
            $mapping->ocworkflowid = $ocworkflowid;
            $mapping->status = self::MAPPING_STATUS_PENDING;
            if (self::update_import_mapping_record($mapping)) {
                return $mapping->id;
            }
        }
        return false;
    }

    /**
     * Validates the mapping object before interacting with db.
     *
     * @param stdClass $mapping the mapping object
     *
     * @return bool whether the mapping is valid.
     */
    public static function validate_mapping_record($mapping) {
        $isvalid = true;
        if (empty($mapping->restoreuid) || empty($mapping->ocinstanceid)
            || empty($mapping->targetcourseid) || empty($mapping->type)) {
            $isvalid = false;
        }
        return $isvalid;
    }
}
