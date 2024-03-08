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
 * LTI module management for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2021 Justus Dieckmann WWU, 2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use core_completion\manager;
use core_plugin_manager;
use course_modinfo;
use mod_opencast\local\opencasttype;
use stdClass;

/**
 * LTI module management for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2021 Justus Dieckmann WWU, 2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activitymodulemanager {


    /**
     * Helperfunction to get the status of the Opencast series feature.
     * This consists of a check if the feature is enabled by the admin.
     *
     * @param int $ocinstanceid Opencast instance id
     * @return boolean
     */
    public static function is_enabled_and_working_for_series($ocinstanceid): bool {
        return get_config('block_opencast', 'addactivityenabled_' . $ocinstanceid) != false &&
            core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
    }

    /**
     * Helperfunction to get the status of the Opencast episodes feature.
     * This consists of a check if the feature is enabled by the admin.
     *
     * @param int $ocinstanceid Opencast instance id
     * @return boolean
     */
    public static function is_enabled_and_working_for_episodes($ocinstanceid) {
        return get_config('block_opencast', 'addactivityepisodeenabled_' . $ocinstanceid) != false &&
            core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
    }

    /**
     * Helperfunction to create the Opencast LTI series module in a course.
     *
     * @param int $courseid
     * @param int $ocinstanceid Opencast instance id
     * @param string $title
     * @param string $seriesid
     * @param int $sectionid
     * @param string $introtext
     * @param int $introformat
     * @param string $availability
     * @param bool $allowdownload
     *
     * @return boolean
     */
    public static function create_module_for_series($courseid, $ocinstanceid, $title, $seriesid, $sectionid = 0, $introtext = '',
                                                    $introformat = FORMAT_HTML, $availability = null, $allowdownload = false) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // If the title or the series is empty, something is wrong.
        if (empty($title) || empty($seriesid)) {
            return false;
        }

        // Get the id of the installed Opencast plugin.
        $pluginid = $DB->get_field('modules', 'id', ['name' => 'opencast']);

        // If the Opencast plugin is not found, something is wrong.
        if (!$pluginid) {
            return false;
        }

        // Get the course.
        $course = get_course($courseid);

        // If the course is not found, something is wrong.
        if (!$course) {
            return false;
        }

        // Create an LTI modinfo object.
        $moduleinfo = self::build_activity_modinfo($pluginid, $course, $ocinstanceid, $title, $sectionid, $seriesid,
            opencasttype::SERIES, $introtext, $introformat, $availability, $allowdownload);

        // Add the Opencast Activity series module to the given course.
        // This does not check any capabilities to add modules to courses by purpose.
        \add_moduleinfo($moduleinfo, $course);

        return true;
    }

    /**
     * Helperfunction to create the Opencast LTI episode module in a course.
     *
     * @param int $courseid
     * @param int $ocinstanceid Opencast instance id
     * @param string $title
     * @param string $episodeuuid
     * @param int $sectionid
     * @param string $introtext
     * @param int $introformat
     * @param string $availability
     * @param bool $allowdownload
     *
     * @return boolean
     */
    public static function create_module_for_episode($courseid, $ocinstanceid, $title, $episodeuuid, $sectionid = 0,
                                                     $introtext = '', $introformat = FORMAT_HTML, $availability = null,
                                                     $allowdownload = false) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // If the title or the episode is empty, something is wrong.
        if (empty($title) || empty($episodeuuid)) {
            return false;
        }

        // Get the id of the installed Opencast plugin.
        $pluginid = $DB->get_field('modules', 'id', ['name' => 'opencast']);

        // If the Opencast plugin is not found, something is wrong.
        if (!$pluginid) {
            return false;
        }

        // Get the course.
        $course = get_course($courseid);

        // If the course is not found, something is wrong.
        if (!$course) {
            return false;
        }

        // Create an Opencast Activity modinfo object.
        $moduleinfo = self::build_activity_modinfo($pluginid, $course, $ocinstanceid, $title, $sectionid, $episodeuuid,
            opencasttype::EPISODE, $introtext, $introformat, $availability, $allowdownload);

        // Add the Opencast Activity episode module to the given course.
        // This does not check any capabilities to add modules to courses by purpose.
        add_moduleinfo($moduleinfo, $course);

        return true;
    }

    /**
     * Helperfunction to create a modinfo class, holding the Opencast LTI module information.
     *
     * @param int $pluginid
     * @param stdClass $course
     * @param int $ocinstanceid Opencast instance id
     * @param string $title
     * @param int $sectionid
     * @param string $opencastid
     * @param int $type opencasttype
     * @param string $introtext
     * @param int $introformat
     * @param string $availability
     * @param bool $allowdownload
     *
     * @return object
     */
    public static function build_activity_modinfo($pluginid, $course, $ocinstanceid, $title, $sectionid, $opencastid, $type,
                                                  $introtext = '', $introformat = FORMAT_HTML, $availability = null,
                                                  $allowdownload = false) {
        global $DB;

        // Create standard class object.
        $moduleinfo = new stdClass();

        // Populate the modinfo object with standard parameters.
        $moduleinfo->modulename = 'opencast';
        $moduleinfo->module = $pluginid;

        $moduleinfo->name = $title;
        $moduleinfo->allowdownload = $allowdownload;
        $moduleinfo->intro = $introtext;
        $moduleinfo->introformat = $introformat;
        if ($moduleinfo->intro != '') {
            $moduleinfo->showdescription = true;
        }

        $moduleinfo->section = $sectionid;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = NOGROUPS;
        $moduleinfo->groupingid = 0;
        $moduleinfo->availability = $availability;

        // Populate the modinfo object with opencast activity specific parameters.
        $moduleinfo->type = $type;
        $moduleinfo->opencastid = $opencastid;
        $moduleinfo->ocinstanceid = $ocinstanceid;

        // Apply completion defaults.
        $module = $DB->get_record('modules', ['name' => 'opencast'], '*', MUST_EXIST);
        $defaults = manager::get_default_completion($course, $module);
        foreach ($defaults as $key => $value) {
            $moduleinfo->$key = $value;
        }

        // Return modinfo.
        return $moduleinfo;
    }

    /**
     * Helperfunction to get the Opencast series module of a course.
     * This includes a sanity check if the stored series module still exists.
     *
     * @param int $ocinstanceid Opencast instance id
     * @param int $courseid
     * @param string $seriesid Series identifier
     *
     * @return int|boolean
     */
    public static function get_module_for_series($ocinstanceid, $courseid, $seriesid) {
        global $DB;

        // Get the Opencast Activity series module id.
        if (get_config('mod_opencast', 'version') >= 2021091200) {
            $instance = $DB->get_field('opencast', 'id', ['course' => $courseid,
                'type' => opencasttype::SERIES, 'opencastid' => $seriesid, 'ocinstanceid' => $ocinstanceid, ], IGNORE_MULTIPLE);
        } else {
            // Ensure backwards compatibility since ocinstanceid does not exist before.
            $instance = $DB->get_field('opencast', 'id', ['course' => $courseid,
                'type' => opencasttype::SERIES, 'opencastid' => $seriesid, ], IGNORE_MULTIPLE);
        }

        // If there is a Opencast Activity series module found.
        if ($instance) {
            // Check if the Opencast Activity series module with the given id really exists.
            $cm = get_coursemodule_from_instance('opencast', $instance, $courseid);

            // If the course module does not exist or is scheduled to be deleted before we return it.
            // Note: This plugin could achieve the same goal by listening to the course_module_deleted event.
            // But this plugin would then have to check _every_ deleted module if it's an Opencast Opencast Activity module.
            // This a big overhead over checking the existence only here when it is really needed.
            if ($cm == false || $cm->deletioninprogress == 1) {

                // Inform the caller.
                return false;
            }
            return $cm->id;
        }
        return false;
    }

    /**
     * Helperfunction to get the Opencast LTI episode module of a particular course in a course.
     *
     * @param int $courseid
     * @param string $episodeuuid
     * @param int $ocinstanceid Opencast instance id
     *
     * @return int|boolean
     */
    public static function get_module_for_episode($courseid, $episodeuuid, $ocinstanceid) {
        global $DB;

        // Get the Opencast Activity series module id.
        if (get_config('mod_opencast', 'version') >= 2021091200) {
            $instance = $DB->get_field('opencast', 'id', ['course' => $courseid,
                'opencastid' => $episodeuuid, 'type' => opencasttype::EPISODE, 'ocinstanceid' => $ocinstanceid, ], IGNORE_MULTIPLE);
        } else {
            $instance = $DB->get_field('opencast', 'id', ['course' => $courseid,
                'opencastid' => $episodeuuid, 'type' => opencasttype::EPISODE, ], IGNORE_MULTIPLE);
        }

        // If there is a Opencast Activity series module found.
        if ($instance) {
            // Check if the Opencast Activity series module with the given id really exists.
            $cm = get_coursemodule_from_instance('opencast', $instance, $courseid);

            // If the course module does not exist or is scheduled to be deleted before we return it.
            // Note: This plugin could achieve the same goal by listening to the course_module_deleted event.
            // But this plugin would then have to check _every_ deleted module if it's an Opencast Opencast Activity module.
            // This a big overhead over checking the existence only here when it is really needed.
            if ($cm == false || $cm->deletioninprogress == 1) {

                // Inform the caller.
                return false;
            }
            return $cm->id;
        }
        return false;
    }

    /**
     * Helperfunction to get the default Opencast Activity series module title.
     * This includes a fallback for the case that the admin has set it to an empty string.
     *
     * @param int $ocinstanceid Opencast instance id
     * @return string
     */
    public static function get_default_title_for_series($ocinstanceid) {
        // Get the default title from the admin settings.
        $defaulttitle = get_config('block_opencast', 'addactivitydefaulttitle_' . $ocinstanceid);

        // Check if the configured default title is empty. This must not happen as a module needs a title.
        if (empty($defaulttitle) || $defaulttitle == '') {
            $defaulttitle = get_string('addactivity_defaulttitle', 'block_opencast');
        }

        // Return the default title.
        return $defaulttitle;
    }

    /**
     * Helperfunction to get the default title for a particular Opencast Activity episode module.
     * This includes a fallback for the case that the episode title is empty.
     *
     * @param int $ocinstanceid Opencast instance id
     * @param string $episodeuuid
     *
     * @return string
     */
    public static function get_default_title_for_episode($ocinstanceid, $episodeuuid) {
        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get the episode information.
        $info = $apibridge->get_opencast_video($episodeuuid);

        // If we did get an error from the APIbridge, there is probably something wrong.
        // However, it's not our job to solve this here. We just have to provide a default title.
        // Thus, let's return the default title from the language pack.
        if ($info->error != 0) {
            return get_string('addactivityepisode_defaulttitle', 'block_opencast');
        }

        // Pick the video title from the information object.
        $episodetitle = $info->video->title;

        // Check if the episode title is empty. This must not happen as a module needs a title.
        // Thus, let's return the default title from the language pack.
        if (empty($episodetitle) || $episodetitle == '') {
            return get_string('addactivityepisode_defaulttitle', 'block_opencast');
        }

        // Finally, return the episode title.
        return $episodetitle;
    }

    /**
     * Helperfunction to get the default intro for a particular Opencast Activity episode module.
     *
     * @param int $ocinstanceid Opencast instance id
     * @param string $episodeuuid
     *
     * @return string
     */
    public static function get_default_intro_for_episode($ocinstanceid, $episodeuuid) {
        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get the episode information.
        $info = $apibridge->get_opencast_video($episodeuuid);

        // If we did get an error from the APIbridge, there is probably something wrong.
        // However, it's not our job to solve this here. We just have to provide a default intro.
        // Thus, let's return an empty string.
        if ($info->error != 0) {
            return '';
        }

        // Pick the video description from the information object.
        $episodeintro = $info->video->description;

        // Check if the episode intro is empty. This isn't a problem.
        // Thus, let's return an empty string.
        if (empty($episodeintro) || $episodeintro == '') {
            return '';
        }

        // As the Opencast video description is a plain-text field which might contain line breaks anyway,
        // thus insert HTML line breaks.
        $episodeintro = nl2br($episodeintro);

        // Finally, return the episode intro.
        return $episodeintro;
    }

    /**
     * Helperfunction to get the section list of a given course as associative array.
     * This includes a fallback for the case that the course format does not use sections at all.
     *
     * @param int $courseid
     *
     * @return array
     */
    public static function get_course_sections($courseid) {
        // Get course format.
        $courseformat = course_get_format($courseid);

        // If the course format does not use sections at all, we are already done.
        if (!$courseformat->uses_sections()) {
            return [];
        }

        // Get list of sections.
        $coursemodinfo = course_modinfo::instance($courseid);
        $sections = $coursemodinfo->get_section_info_all();

        // Extract section titles and build section menu.
        $sectionmenu = [];
        foreach ($sections as $id => $section) {
            $sectionmenu[$id] = get_section_name($courseid, $id);
        }

        // Finally, return the course section array.
        return $sectionmenu;
    }

    /**
     * Helperfunction to get Opencast series modules within a course which are linking to the Opencast series of another course.
     * This especially catches modules which have been imported from one course to another course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid The course where the module is located.
     * @param int $referencedcourseid The course where the module is pointing to.
     *
     * @return array of course module IDs. The course module ID is used as array key, the references series ID as array value.
     */
    public static function get_modules_for_series_linking_to_other_course($ocinstanceid, $courseid, $referencedcourseid) {
        global $DB;

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        $modules = [];

        // Get the course series of the referenced course.
        foreach ($apibridge->get_course_series($referencedcourseid) as $series) {
            $seriesmodules = $DB->get_records('opencast', ['ocinstanceid' => $ocinstanceid,
                'type' => opencasttype::SERIES,
                'course' => $courseid,
                'opencastid' => $series->series, ]);

            // If there are any existing series modules in this course.
            if (count($seriesmodules) > 0) {
                // Iterate over modules.
                foreach ($seriesmodules as $instance) {
                    $cm = get_coursemodule_from_instance('opencast', $instance->id, $courseid);
                    // Remember the series module to be returned.
                    $modules[$cm->id] = $series->series;
                }
            }
        }

        // Return the module(s) ids.
        return $modules;
    }

    /**
     * Helperfunction to get Opencast episode modules within a course which are linking to a video within the Opencast series
     * of another course.
     * This function is just an iterator for get_modules_for_episode_linking_to_other_course(), iterating over all course videos.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $modulecourseid The course where the module is located.
     * @param int $referencedcourseid The course where the module is pointing to.
     * @param array|null $onlytheseepisodes (optional) The array of the episode identifiers.
     *                                      If given, only these identifiers will be evaluated.
     *                                      If not given, all course videos will be evaluated.
     *
     * @return array
     */
    public static function get_modules_for_episodes_linking_to_other_course($ocinstanceid, $modulecourseid, $referencedcourseid,
                                                                            $onlytheseepisodes = null) {
        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Initialize modules to be returned as empty array.
        $modules = [];

        // Get the course series of the referenced course.
        foreach ($apibridge->get_course_series($referencedcourseid) as $series) {
            // Get episodes which are located in the referenced series.
            $coursevideos = $apibridge->get_series_videos($series->series);

            // Iterate over episodes.
            foreach ($coursevideos->videos as $video) {
                // Proceed only if we have to check this particular video.
                if ($onlytheseepisodes !== null && !in_array($video->identifier, $onlytheseepisodes)) {
                    continue;
                }

                // Check each episode individually.
                $episodemodules = self::get_modules_for_episode_linking_to_other_course($ocinstanceid,
                    $modulecourseid, $video->identifier);

                // And add the result to the array of modules.
                $modules += $episodemodules;
            }
        }

        // Return the module(s) ids.
        return $modules;
    }

    /**
     * Helperfunction to get Opencast episode modules within a course which are linking to a video within the Opencast series
     * of another course.
     * This especially catches modules which have been imported from one course to another course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid The course where the module is located.
     * @param int $referencedepisodeid The episode id where the module is pointing to.
     *
     * @return array
     */
    public static function get_modules_for_episode_linking_to_other_course($ocinstanceid, $courseid, $referencedepisodeid) {
        global $DB;

        $episodemodules = $DB->get_records('opencast', ['ocinstanceid' => $ocinstanceid,
            'course' => $courseid,
            'type' => opencasttype::EPISODE,
            'opencastid' => $referencedepisodeid, ]);

        // Initialize modules to be returned as empty array.
        $modules = [];

        // If there are any existing episode modules in this course.
        if (count($episodemodules) > 0) {
            // Iterate over modules.
            foreach ($episodemodules as $instance) {
                $cm = get_coursemodule_from_instance('opencast', $instance->id, $courseid);
                // Remember the episode module to be returned.
                $modules[$cm->id] = $referencedepisodeid;
            }
        }

        // Return the module(s) ids.
        return $modules;
    }

    /**
     * Helperfunction to cleanup the Opencast activity episode modules for a given episode module from the job list in the database.
     * This especially cleans up modules which have been imported from one course to another course.
     * This function is primarily called by the \block_opencast\task\cleanup_imported_episodes_cron scheduled task.
     * That's why it does not do any capability check anymore, this must have been done before the task was scheduled.
     *
     * @param int $courseid The course which is cleaned up.
     * @param array $episodemodules The array of episodemodules to be cleaned up.
     * @param string $episodeid The episode ID where the modules should be pointing to in the end.
     *
     * @return bool
     */
    public static function cleanup_episode_modules($courseid, $episodemodules, $episodeid) {
        global $CFG, $DB;

        // If there aren't any modules to be cleaned up given, return.
        if (count($episodemodules) < 1) {
            return true;
        }

        $success = true;
        foreach ($episodemodules as $module) {
            $cm = get_fast_modinfo($courseid)->get_cm($module);
            $success = $success && $DB->set_field('opencast', 'opencastid', $episodeid,
                    ['id' => $cm->instance]);
        }

        return $success;
    }

    /**
     * Helperfunction to cleanup the Opencast series modules within a course.
     * This especially cleans up modules which have been imported from one course to another course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $modulecourseid The course which is cleaned up.
     * @param string[] $referencedseries Series identifiers
     *
     * @return bool
     */
    public static function cleanup_series_modules($ocinstanceid, $modulecourseid, $referencedseries) {
        global $DB;

        // Get old series id.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get new series id.
        $courseseries = $apibridge->get_stored_seriesid($modulecourseid);

        $success = true;
        foreach ($referencedseries as $series) {
            $success = $success && $DB->set_field('opencast', 'opencastid', $courseseries, ['ocinstanceid' => $ocinstanceid,
                    'type' => opencasttype::SERIES, 'opencastid' => $series, 'course' => $modulecourseid, ]);
        }

        return $success;
    }

    /**
     * Looks up for series Activity modules in a new (imported) course that has faulty (old) series id.
     * Repairs the faulty Activity module by replacing the new series id in the db record.
     * @see importvideosmanager::fix_imported_series_modules_in_new_course() After the restore is completed.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid New course id.
     * @param string $sourceseriesid Old series id.
     * @param string $sourcenewseriesidseriesid New series id.
     *
     * @return void
     */
    public static function fix_imported_series_modules_in_new_course(
        $ocinstanceid, $courseid, $sourceseriesid, $newseriesid) {
        global $DB, $CFG;
        // Require grade library. For an unknown reason, this is needed when updating the module.
        require_once($CFG->libdir . '/gradelib.php');

        // Find the faulty series activity modules in new course.
        $seriesmodules = $DB->get_records('opencast', [
            'ocinstanceid' => $ocinstanceid,
            'type' => opencasttype::SERIES,
            'course' => $courseid,
            'opencastid' => $sourceseriesid
        ]);

        // IF anything has been found.
        if (!empty($seriesmodules)) {
            foreach ($seriesmodules as $instance) {
                // We also check the existance of the course moulde.
                $cm = get_coursemodule_from_instance('opencast', $instance->id, $courseid);
                if (!empty($cm)) {
                    // We replace the old with new series id.
                    $DB->set_field('opencast', 'opencastid', $newseriesid,
                        ['id' => $instance->id]);
                }
            }
        }
    }

    /**
     * Looks up for episode Activity modules in a new (imported) course that has faulty (old) event id.
     * Repairs the faulty Activity module by replacing the new event id in the db record.
     * @see importvideosmanager::fix_imported_episode_modules_in_new_course() in task "process_duplicated_event_module_fix"
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $targetcourseid New course id.
     * @param string $sourceeventid Old event id.
     * @param string $duplicatedeventid New event id.
     *
     * @return void
     */
    public static function fix_imported_episode_modules_in_new_course(
        $ocinstanceid, $targetcourseid, $sourceeventid, $duplicatedeventid
    ) {
        global $DB, $CFG;
        // Require grade library. For an unknown reason, this is needed when updating the module.
        require_once($CFG->libdir . '/gradelib.php');

        $episodemodules = $DB->get_records('opencast', [
            'ocinstanceid' => $ocinstanceid,
            'course' => $targetcourseid,
            'type' => opencasttype::EPISODE,
            'opencastid' => $sourceeventid
        ]);

        if (count($episodemodules) > 0) {
            // Iterate over modules.
            foreach ($episodemodules as $instance) {
                // We also check the existance of the course moulde.
                $cm = get_coursemodule_from_instance('opencast', $instance->id, $targetcourseid);
                if (!empty($cm)) {
                    $DB->set_field('opencast', 'opencastid', $duplicatedeventid,
                        ['id' => $instance->id]);
                }
            }
        }
    }
}
