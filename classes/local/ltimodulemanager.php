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
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use context_course;
use core_completion\manager;
use course_modinfo;
use Exception;
use moodle_exception;
use stdClass;
use tool_opencast\local\settings_api;

/**
 * LTI module management for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ltimodulemanager {


    /**
     * Helperfunction to get the list of available preconfigured LTI tools.
     * Please note that this function does not filter for Opencast LTI tools only,
     * it just fetches all available preconfigured LTI tools.
     *
     * @return array
     */
    public static function get_preconfigured_tools() {
        global $CFG;

        // Require LTI library.
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        // Get configured tools and filter them for tools which are configured by the admin.
        $types = \lti_filter_get_types(get_site()->id);
        $configuredtools = lti_filter_tool_types($types, LTI_TOOL_STATE_CONFIGURED);

        // Initialize array of tool to be returned.
        $tools = [];

        // Iterate over configured tools and fill the array to be returned.
        foreach ($configuredtools as $ct) {
            $tools[$ct->id] = $ct->name;
        }

        // Return the array of tools.
        return $tools;
    }

    /**
     * Helperfunction to get the preconfigured tool to be used for Opencast series.
     * This includes a sanity check if the configured tool is valid.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return int|boolean
     */
    public static function get_preconfigured_tool_for_series($ocinstanceid) {
        // Get the preconfigured LTI tool to be used.
        $toolid = get_config('block_opencast', 'addltipreconfiguredtool_' . $ocinstanceid);

        // Get the list of available preconfigured LTI tools.
        $tools = self::get_preconfigured_tools();

        // If the preconfigured LTI tool to be used is not in the list of available tools, something is wrong.
        if (!array_key_exists($toolid, $tools)) {
            // Reset the plugin config.
            set_config('block_opencast', null, 'addltipreconfiguredtool_' . $ocinstanceid);

            // Inform the caller.
            return false;
        }

        // Return the preconfigured LTI tool to be used.
        return $toolid;
    }

    /**
     * Helperfunction to get the preconfigured tool to be used for Opencast episodes.
     * This includes a sanity check if the configured tool is valid.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return int|boolean
     */
    public static function get_preconfigured_tool_for_episode($ocinstanceid) {
        // Get the preconfigured LTI tool to be used.
        $toolid = get_config('block_opencast', 'addltiepisodepreconfiguredtool_' . $ocinstanceid);

        // Get the list of available preconfigured LTI tools.
        $tools = self::get_preconfigured_tools();

        // If the preconfigured LTI tool to be used is not in the list of available tools, something is wrong.
        if (!array_key_exists($toolid, $tools)) {
            // Reset the plugin config.
            set_config('block_opencast', null, 'addltiepisodepreconfiguredtool_' . $ocinstanceid);

            // Inform the caller.
            return false;
        }

        // Return the preconfigured LTI tool to be used.
        return $toolid;
    }

    /**
     * Helperfunction to get the status of the Opencast series feature.
     * This consists of a check if the feature is enabled by the admin and a sanity check if the configured tool is valid.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return boolean
     */
    public static function is_enabled_and_working_for_series($ocinstanceid) {
        // Get the status of the feature.
        $config = get_config('block_opencast', 'addltienabled_' . $ocinstanceid);

        // If the setting is false, then the feature is not working.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        return self::is_working_for_series($ocinstanceid);
    }

    /**
     * Helperfunction to get the status of the Opencast series feature.
     * This is a sanity check if the configured tool is valid.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return boolean
     */
    public static function is_working_for_series($ocinstanceid) {
        // Get the preconfigured tool.
        $tool = self::get_preconfigured_tool_for_series($ocinstanceid);

        // If the tool is empty, then the feature is not working.
        if ($tool == false) {
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

        // The feature is working.
        return true;
    }

    /**
     * Helperfunction to get the status of the Opencast episodes feature.
     * This consists of a check if the feature is enabled by the admin and a sanity check if the configured tool is valid.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return boolean
     */
    public static function is_enabled_and_working_for_episodes($ocinstanceid) {
        // Remember the status for subsequent calls.
        static $enabledandworking = null;

        // If we know the status already, inform the caller directly.
        if ($enabledandworking !== null) {
            return $enabledandworking;
        }

        // If we don't know the status yet, check the status of the feature.
        if ($enabledandworking === null) {
            // Get the status of the feature.
            $config = get_config('block_opencast', 'addltiepisodeenabled_' . $ocinstanceid);

            // If the setting is false, then the feature is not working.
            if ($config == false) {
                // Remember the status.
                $enabledandworking = false;
            }
        }

        // Check if feature is working.
        if ($enabledandworking !== false) {
            $enabledandworking = self::is_working_for_episodes($ocinstanceid);
        }

        // Inform the caller.
        return $enabledandworking;
    }

    /**
     * Helperfunction to get the status of the Opencast episodes feature.
     * This is a sanity check if the configured tool is valid.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return boolean
     */
    public static function is_working_for_episodes($ocinstanceid) {
        // Remember the status for subsequent calls.
        static $working = null;

        // If we know the status already, inform the caller directly.
        if ($working !== null) {
            return $working;
        }

        // If we don't know the status yet, check the preconfigured tool.
        if ($working === null) {
            // Get the preconfigured tool.
            $tool = self::get_preconfigured_tool_for_episode($ocinstanceid);

            // If the tool is empty, then the feature is not working.
            if ($tool == false) {
                // Inform the caller.
                $working = false;
            }
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

        // If everything was fine up to now, we should be sure that the feature is working.
        if ($working !== false) {
            $working = true;
        }

        // Inform the caller.
        return $working;
    }

    /**
     * Helperfunction to create the Opencast LTI series module in a course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $title
     * @param string $seriesid
     * @param int $sectionid
     * @param string $introtext
     * @param int $introformat
     * @param string $availability
     *
     * @return boolean
     */
    public static function create_module_for_series($ocinstanceid, $courseid, $title, $seriesid, $sectionid = 0, $introtext = '',
                                                    $introformat = FORMAT_HTML, $availability = null) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // If the title or the series is empty, something is wrong.
        if (empty($title) || empty($seriesid)) {
            return false;
        }

        // Get the id of the installed LTI plugin.
        $pluginid = $DB->get_field('modules', 'id', ['name' => 'lti']);

        // If the LTI plugin is not found, something is wrong.
        if (!$pluginid) {
            return false;
        }

        // Get the course.
        $course = $DB->get_record('course', ['id' => $courseid]);

        // If the course is not found, something is wrong.
        if (!$course) {
            return false;
        }

        // Get the preconfigured LTI tool to be used.
        $toolid = self::get_preconfigured_tool_for_series($ocinstanceid);

        // If the preconfigured LTI tool to be used is not configured correctly, something is wrong.
        if ($toolid == false) {
            return false;
        }

        // Create an LTI modinfo object.
        $moduleinfo = self::build_lti_modinfo($pluginid, $course, $title, $sectionid, $toolid, 'series=' . $seriesid,
            $introtext, $introformat, $availability);

        // Add the LTI series module to the given course (this doesn't check any capabilities to add modules to courses by purpose).
        $modulecreated = \add_moduleinfo($moduleinfo, $course);

        // Remember the module id.
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->cmid = $modulecreated->coursemodule;
        $record->ocinstanceid = $ocinstanceid;
        $record->seriesid = $seriesid;
        $DB->insert_record('block_opencast_ltimodule', $record);

        return true;
    }

    /**
     * Helperfunction to create the Opencast LTI episode module in a course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $title
     * @param string $episodeuuid
     * @param int $sectionid
     * @param string $introtext
     * @param int $introformat
     * @param string $availability
     *
     * @return boolean
     */
    public static function create_module_for_episode($ocinstanceid, $courseid, $title, $episodeuuid, $sectionid = 0,
                                                     $introtext = '', $introformat = FORMAT_HTML,
                                                     $availability = null) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // If the title or the episode is empty, something is wrong.
        if (empty($title) || empty($episodeuuid)) {
            return false;
        }

        // Get the id of the installed LTI plugin.
        $pluginid = $DB->get_field('modules', 'id', ['name' => 'lti']);

        // If the LTI plugin is not found, something is wrong.
        if (!$pluginid) {
            return false;
        }

        // Get the course.
        $course = $DB->get_record('course', ['id' => $courseid]);

        // If the course is not found, something is wrong.
        if (!$course) {
            return false;
        }

        // Get the preconfigured LTI tool to be used.
        $toolid = self::get_preconfigured_tool_for_episode($ocinstanceid);

        // If the preconfigured LTI tool to be used is not configured correctly, something is wrong.
        if ($toolid == false) {
            return false;
        }

        // Create an LTI modinfo object.
        $moduleinfo = self::build_lti_modinfo($pluginid, $course, $title, $sectionid, $toolid, 'id=' . $episodeuuid, $introtext,
            $introformat, $availability);

        // Add the LTI episode module to the given course. This doesn't check any capabilities to add modules to courses by purpose.
        $modulecreated = \add_moduleinfo($moduleinfo, $course);

        // Remember the module id.
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->episodeuuid = $episodeuuid;
        $record->cmid = $modulecreated->coursemodule;
        $record->ocinstanceid = $ocinstanceid;
        $DB->insert_record('block_opencast_ltiepisode', $record);

        return true;
    }

    /**
     * Helperfunction to create a modinfo class, holding the Opencast LTI module information.
     *
     * @param int $pluginid
     * @param stdClass $course
     * @param string $title
     * @param int $sectionid
     * @param int $toolid
     * @param string $instructorcustomparameters
     * @param string $introtext
     * @param int $introformat
     * @param string $availability
     *
     * @return object
     */
    public static function build_lti_modinfo($pluginid, $course, $title, $sectionid, $toolid, $instructorcustomparameters,
                                             $introtext = '', $introformat = FORMAT_HTML, $availability = null) {
        global $DB;

        // Create standard class object.
        $moduleinfo = new stdClass();

        // Populate the modinfo object with standard parameters.
        $moduleinfo->modulename = 'lti';
        $moduleinfo->module = $pluginid;

        $moduleinfo->name = $title;
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

        // Apply completion defaults.
        $module = $DB->get_record('modules', ['name' => 'opencast']);
        $defaults = manager::get_default_completion($course, $module);
        if ($module) {
            foreach ($defaults as $key => $value) {
                $moduleinfo->$key = $value;
            }
        }

        // Populate the modinfo object with LTI specific parameters.
        $moduleinfo->typeid = $toolid;
        $moduleinfo->showtitlelaunch = true;
        $moduleinfo->instructorcustomparameters = $instructorcustomparameters;

        // Return modinfo.
        return $moduleinfo;
    }

    /**
     * Helperfunction to get the Opencast LTI series module of a course.
     * This module will be picked from the block_opencast_ltimodule table, i.e. it delivers the only module linking to the course
     * series which must have been created from within the Opencast videos overview page.
     * This includes a sanity check if the stored LTI series module still exists.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $series
     *
     * @return int|boolean
     */
    public static function get_module_for_series($ocinstanceid, $courseid, $series) {
        global $DB;

        // Get the LTI series module id.
        $moduleid = $DB->get_field('block_opencast_ltimodule', 'cmid', ['ocinstanceid' => $ocinstanceid,
            'courseid' => $courseid, 'seriesid' => $series, ]);

        // If there is a LTI series module found.
        if ($moduleid) {
            // Check if the LTI series module with the given id really exists.
            $cm = get_coursemodule_from_id('lti', $moduleid, $courseid);

            // If the course module does not exist or is scheduled to be deleted before we return it.
            // Note: This plugin could achieve the same goal by listening to the course_module_deleted event.
            // But this plugin would then have to check _every_ deleted module if it's an Opencast LTI module.
            // This a big overhead over checking the existence only here when it is really needed.
            if ($cm == false || $cm->deletioninprogress == 1) {
                // Clear the entry from the block_opencast_ltimodule table.
                $DB->delete_records('block_opencast_ltimodule', ['courseid' => $courseid]);

                // Inform the caller.
                return false;
            }
        }

        // Return the LTI module id.
        return $moduleid;
    }

    /**
     * Helperfunction to get Opencast LTI series modules within a course which are linking to the Opencast series of another course.
     * These modules will be searched in the mod_lti table, i.e. the function delivers all modules which are linking to the
     * given course' series.
     * This especially catches modules which have been imported from one course to another course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $modulecourseid The course where the module is located.
     * @param int $referencedcourseid The course where the module is pointing to.
     *
     * @return array of course module IDs. The course module ID is used as array key, the references series ID as array value.
     */
    public static function get_modules_for_series_linking_to_other_course($ocinstanceid, $modulecourseid, $referencedcourseid) {
        global $DB;

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Initialize modules to be returned as empty array.
        $modules = [];

        // Get the id of the preconfigured tool.
        $toolid = self::get_preconfigured_tool_for_series($ocinstanceid);

        // Get the course series of the referenced course.
        foreach ($apibridge->get_course_series($referencedcourseid) as $series) {
            // Get the LTI series module(s) which point to the series.
            // If there is more than one module, the list will be ordered by the time when the module was added to the course.
            // The oldest module is probably the module which should be kept when the modules are cleaned up later,
            // the newer ones will probably be from additional course content imports.
            $sql = 'SELECT cm.id AS cmid FROM {lti} l ' .
                'JOIN {course_modules} cm ' .
                'ON l.id = cm.instance ' .
                'WHERE l.typeid = :toolid ' .
                'AND cm.course = :course ' .
                'AND ' . $DB->sql_like('l.instructorcustomparameters', ':referencedseriesid') .
                ' ORDER BY cm.added ASC';
            $params = ['toolid' => $toolid,
                'course' => $modulecourseid,
                'referencedseriesid' => '%' . $series->series . '%', ];
            $seriesmodules = $DB->get_fieldset_sql($sql, $params);

            // If there are any existing series modules in this course.
            if (count($seriesmodules) > 0) {
                // Iterate over modules.
                foreach ($seriesmodules as $s) {
                    // Remember the series module to be returned.
                    $modules[$s] = $series->series;
                }
            }
        }

        // Return the LTI module(s) ids.
        return $modules;
    }

    /**
     * Helperfunction to cleanup the Opencast LTI series modules within a course.
     * This especially cleans up modules which have been imported from one course to another course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $modulecourseid The course which is cleaned up.
     * @param int $referencedcourseid The course where the modules are pointing to.
     * @param string $duplicatedseries
     *
     * @return bool
     */
    public static function cleanup_series_modules($ocinstanceid, $modulecourseid, $referencedcourseid, $duplicatedseries) {
        global $CFG, $DB;

        // Require grade library. For an unknown reason, this is needed when updating the module.
        require_once($CFG->libdir . '/gradelib.php');

        // If the user is not allowed to add series modules to the target course at all, return.
        $coursecontext = context_course::instance($modulecourseid);
        if (has_capability('block/opencast:addlti', $coursecontext) != true) {
            return false;
        }

        // Get the existing series modules in the course.
        $referencedseriesmodules = self::get_modules_for_series_linking_to_other_course($ocinstanceid,
            $modulecourseid, $referencedcourseid);

        $referencedseriesmodules = array_filter($referencedseriesmodules, function ($entry) use ($duplicatedseries) {
            return in_array($entry, $duplicatedseries);
        });

        // If there aren't any modules in the course to be cleaned up, return.
        if (count($referencedseriesmodules) < 1) {
            return true;
        }

        // Get an APIbridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get the course series of the referenced course.
        $courseseries = $apibridge->get_stored_seriesid($modulecourseid);

        // Get Opencast LTI series module in this course which point to the course's series.
        $courseseriesmodule = self::get_module_for_series($ocinstanceid, $modulecourseid, $courseseries);

        // If there isn't a series module for this course' series in this course yet.
        if ($courseseriesmodule == false) {
            // Get the module ID of the first existing series module (which is most probably the one the teacher wants to keep)
            // for the referenced course.
            reset($referencedseriesmodules);
            $seriesmoduleid = key($referencedseriesmodules); // From PHP 7.3 on, there would also be array_key_first(),
            // but we want to keep this code as backwards-compatible as possible.

            // Gather more information about this module so that we can update the module info in the end.
            $seriesmoduleobject = get_coursemodule_from_id('lti', $seriesmoduleid, $modulecourseid);
            $courseobject = get_course($modulecourseid);
            list($unusedcm, $unusedcontext, $unusedmodule, $seriesmoduledata, $unusedcw) =
                get_moduleinfo_data($seriesmoduleobject, $courseobject);

            // Replace the series identifier in the module info.
            $seriesmoduledata->instructorcustomparameters = 'series=' . $courseseries;

            // Update the series identifier within the series module.
            update_module($seriesmoduledata);

            // Remember this series module id as the series module of the course.
            $record = new stdClass();
            $record->courseid = $modulecourseid;
            $record->cmid = $seriesmoduleid;
            $record->ocinstanceid = $ocinstanceid;
            $record->seriesid = $courseseries;
            $DB->insert_record('block_opencast_ltimodule', $record);

            // Remove this module from the array of existing modules and preserve the keys.
            if (count($referencedseriesmodules) > 1) {
                $referencedseriesmodules = array_slice($referencedseriesmodules, 1, null, true);
            } else {
                $referencedseriesmodules = [];
            }
        }

        // Now, there either existed a series module for this course already and we just have to delete the modules which point
        // to the referenced course.
        // Or there wasn't a module for this course, but we rewrote the first module for the referenced course and removed it from
        // the array afterwards.
        // Either way, we can delete all remaining existing modules for the referenced course now.
        foreach ($referencedseriesmodules as $cmid => $seriesid) {
            try {
                // Delete the module.
                course_delete_module($cmid);
            } catch (Exception $e) {
                // Something must have failed, return.
                return false;
            }
        }

        // Return success.
        return true;
    }

    /**
     * Helperfunction to get the Opencast LTI episode modules of a course.
     * This includes a sanity check if the stored LTI episode modules still exists.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     *
     * @return array
     */
    public static function get_modules_for_episodes($ocinstanceid, $courseid) {
        global $DB;

        // Get the LTI episode module ids.
        $modules = $DB->get_records_menu('block_opencast_ltiepisode', ['ocinstanceid' => $ocinstanceid,
            'courseid' => $courseid, ], '', 'episodeuuid, cmid');

        // Return the LTI module ids.
        return $modules;
    }

    /**
     * Helperfunction to pick the Opencast LTI episode module of a particular episode from a given associative array of modules.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param array $modules
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return int|boolean
     */
    public static function pick_module_for_episode($ocinstanceid, $modules, $courseid, $episodeuuid) {
        global $DB;

        // If there isn't an episode for the given episode.
        if (!array_key_exists($episodeuuid, $modules)) {
            // Inform the caller.
            return false;
        }

        // Pick the requested module id.
        $moduleid = $modules[$episodeuuid];

        // If there is a LTI episode module found.
        if ($moduleid) {
            // Check if the LTI episode module with the given id really exists.
            $cm = get_coursemodule_from_id('lti', $moduleid, $courseid);

            // If the course module does not exist or is scheduled to be deleted before we return it.
            // Note: This plugin could achieve the same goal by listening to the course_module_deleted event.
            // But this plugin would then have to check _every_ deleted module if it's an Opencast LTI module.
            // This a big overhead over checking the existence only here when it is really needed.
            if ($cm == false || $cm->deletioninprogress == 1) {
                // Clear the entry from the block_opencast_ltimodule table.
                $DB->delete_records('block_opencast_ltiepisode',
                    ['episodeuuid' => $episodeuuid, 'ocinstanceid' => $ocinstanceid]);

                // Inform the caller.
                return false;
            }
        }

        // Return the LTI module id.
        return $moduleid;
    }

    /**
     * Helperfunction to get the Opencast LTI episode module of a particular course in a course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return int|boolean
     */
    public static function get_module_for_episode($ocinstanceid, $courseid, $episodeuuid) {
        // Get the existing modules of the course.
        $modules = self::get_modules_for_episodes($ocinstanceid, $courseid);

        // Pick the module for the given episode.
        $moduleid = self::pick_module_for_episode($ocinstanceid, $modules, $courseid, $episodeuuid);

        // Return the LTI module id.
        return $moduleid;
    }

    /**
     * Helperfunction to get Opencast LTI episode modules within a course which are linking to a video within the Opencast series
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
                if ($onlytheseepisodes == null || !in_array($video->identifier, $onlytheseepisodes)) {
                    continue;
                }

                // Check each episode individually.
                $episodemodules = self::get_modules_for_episode_linking_to_other_course($ocinstanceid,
                    $modulecourseid, $video->identifier);

                // And add the result to the array of modules.
                $modules += $episodemodules;
            }
        }

        // Return the LTI module(s) ids.
        return $modules;
    }

    /**
     * Helperfunction to get Opencast LTI episode modules within a course which are linking to a video within the Opencast series
     * of another course.
     * These modules will be searched in the mod_lti table, i.e. the function delivers all modules which are linking to the
     * given episode.
     * This especially catches modules which have been imported from one course to another course.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $modulecourseid The course where the module is located.
     * @param int $referencedepisodeid The episode id where the module is pointing to.
     *
     * @return array
     */
    public static function get_modules_for_episode_linking_to_other_course($ocinstanceid, $modulecourseid, $referencedepisodeid) {
        global $DB;

        // Get the id of the preconfigured tool.
        $toolid = self::get_preconfigured_tool_for_episode($ocinstanceid);

        // Initialize modules to be returned as empty array.
        $modules = [];

        // Get the LTI episode module(s) which point to the episode.
        $sql = 'SELECT cm.id AS cmid FROM {lti} l ' .
            'JOIN {course_modules} cm ' .
            'ON l.id = cm.instance ' .
            'WHERE l.typeid = :toolid ' .
            'AND cm.course = :course ' .
            'AND ' . $DB->sql_like('l.instructorcustomparameters', ':referencedepisodeid');
        $params = ['toolid' => $toolid,
            'course' => $modulecourseid,
            'referencedepisodeid' => '%' . $referencedepisodeid . '%', ];
        $episodemodules = $DB->get_fieldset_sql($sql, $params);

        // If there are any existing episode modules in this course.
        if (count($episodemodules) > 0) {
            // Iterate over modules.
            foreach ($episodemodules as $e) {
                // Remember the episode module to be returned.
                $modules[$e] = $referencedepisodeid;
            }
        }

        // Return the LTI module(s) ids.
        return $modules;
    }

    /**
     * Helperfunction to cleanup the Opencast LTI episode modules for a given episode module from the job list in the database.
     * This especially cleans up modules which have been imported from one course to another course.
     * This function is primarily called by the \block_opencast\task\cleanup_imported_episodes_cron scheduled task.
     * That's why it does not do any capability check anymore, this must have been done before the task was scheduled.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $modulecourseid The course which is cleaned up.
     * @param array $episodemodules The array of episodemodules to be cleaned up.
     * @param string $episodeid The episode ID where the modules should be pointing to in the end.
     *
     * @return bool
     */
    public static function cleanup_episode_modules($ocinstanceid, $modulecourseid, $episodemodules, $episodeid) {
        global $CFG, $DB;

        // Require course module library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // Require grade library. For an unknown reason, this is needed when updating the module.
        require_once($CFG->libdir . '/gradelib.php');

        // If there aren't any modules to be cleaned up given, return.
        if (count($episodemodules) < 1) {
            return true;
        }

        // Get Opencast LTI episode module in this course which points to the given episode.
        $courseepisodemodule = self::get_module_for_episode($ocinstanceid, $modulecourseid, $episodeid);

        // If there isn't an episode module for the given episode in this course yet.
        if ($courseepisodemodule == false) {
            // Get the module ID of the first existing episode module (which is most probably the one the teacher wants to keep)
            // for the referenced episode.
            $episodemoduleid = reset($episodemodules);

            // Gather more information about this module so that we can update the module info in the end.
            $episodemoduleobject = get_coursemodule_from_id('lti', $episodemoduleid, $modulecourseid);
            $courseobject = get_course($modulecourseid);
            list($unusedcm, $unusedcontext, $unusedmodule, $episodemoduledata, $unusedcw) =
                get_moduleinfo_data($episodemoduleobject, $courseobject);

            // Replace the episode identifier in the module info.
            $episodemoduledata->instructorcustomparameters = 'id=' . $episodeid;

            // Update the episode identifier within the episode module.
            update_module($episodemoduledata);

            // Remember this episode module id as the episode module of the course.
            $record = new stdClass();
            $record->courseid = $modulecourseid;
            $record->episodeuuid = $episodeid;
            $record->cmid = $episodemoduleid;
            $record->ocinstanceid = $ocinstanceid;
            $DB->insert_record('block_opencast_ltiepisode', $record);

            // Remove this module from the array of existing modules and preserve the keys.
            if (count($episodemodules) > 1) {
                $episodemodules = array_slice($episodemodules, 1, null, true);
            } else {
                $episodemodules = [];
            }
        }

        // Now, there either existed an episode module for this course already and we just have to delete the modules which point
        // to the referenced course.
        // Or there wasn't a module for this course, but we rewrote the first module for the referenced course and removed it from
        // the array afterwards.
        // Either way, we can delete all remaining existing modules for the referenced course now.
        foreach ($episodemodules as $cmid) {
            try {
                // Delete the module.
                course_delete_module($cmid);
            } catch (Exception $e) {
                // Something must have failed, return.
                return false;
            }
        }

        // Return success.
        return true;
    }

    /**
     * Helperfunction to get the default Opencast LTI series module title.
     * This includes a fallback for the case that the admin has set it to an empty string.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return string
     */
    public static function get_default_title_for_series($ocinstanceid) {
        // Get the default title from the admin settings.
        $defaulttitle = get_config('block_opencast', 'addltidefaulttitle_' . $ocinstanceid);

        // Check if the configured default title is empty. This must not happen as a module needs a title.
        if (empty($defaulttitle) || $defaulttitle == '') {
            $defaulttitle = get_string('addlti_defaulttitle', 'block_opencast');
        }

        // Return the default title.
        return $defaulttitle;
    }

    /**
     * Helperfunction to get the default title for a particular Opencast LTI episode module.
     * This includes a fallback for the case that the episode title is empty.
     *
     * @param int $ocinstanceid Opencast instance id.
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
            return get_string('addltiepisode_defaulttitle', 'block_opencast');
        }

        // Pick the video title from the information object.
        $episodetitle = $info->video->title;

        // Check if the episode title is empty. This must not happen as a module needs a title.
        // Thus, let's return the default title from the language pack.
        if (empty($episodetitle) || $episodetitle == '') {
            return get_string('addltiepisode_defaulttitle', 'block_opencast');
        }

        // Finally, return the episode title.
        return $episodetitle;
    }

    /**
     * Helperfunction to get the default intro for a particular Opencast LTI episode module.
     *
     * @param int $ocinstanceid Opencast instance id.
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
     * Helperfunction to check if the given episode ID is a valid episode ID.
     *
     * We do not validate if the given episode is really published anywhere.
     * But we validate if the given episode UUID is really a UUID.
     * The test code is borrowed from /lib/tests/setuplib_tests.php.
     *
     * @param string $episodeid
     *
     * @return bool
     */
    public static function is_valid_episode_id($episodeid) {
        $uuidv4pattern = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
        if (strlen($episodeid) != 36 || preg_match($uuidv4pattern, $episodeid) !== 1) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Updates the existing LTI modules records.
     * This method is to update records in opencast lti modules tables against the actual course modules.
     * This function is intended to be called by the \block_opencast\task\cleanup_lti_module_cron scheduled task.
     *
     * @return int The number of updated lti modules.
     */
    public static function update_existing_lti_modules() {
        global $DB, $CFG;

        // We need mod and grade libraries to get module information.
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        // Keep track of updated records number.
        $updatedmodulesnum = 0;

        // Getting opencast instances.
        $ocinstances = settings_api::get_ocinstances();

        // Initializing the array to hold episode lti tools.
        $episodetoolids = [];
        // Initializing the array to hold series lti tools.
        $seriestoolids = [];
        // Get the lti tools based on ocinstances.
        foreach ($ocinstances as $ocinstance) {
            $isaddltiepisodeenabled = get_config('block_opencast', 'addltiepisodeenabled_' . $ocinstance->id);
            if (!empty($isaddltiepisodeenabled)) {
                $episodetoolid = self::get_preconfigured_tool_for_episode($ocinstance->id);
                if (!empty($episodetoolid) && !array_key_exists($episodetoolid, $episodetoolids)) {
                    $episodetoolids[$episodetoolid] = $ocinstance->id;
                }
            }
            $isaddltienabled = get_config('block_opencast', 'addltienabled_' . $ocinstance->id);
            if ($isaddltienabled) {
                $seriestoolid = self::get_preconfigured_tool_for_series($ocinstance->id);
                if (!empty($seriestoolid) && !array_key_exists($seriestoolid, $seriestoolids)) {
                    $seriestoolids[$seriestoolid] = $ocinstance->id;
                }
            }
        }

        // Opencast lti episode records.
        $existingepisodemodules = $DB->get_records('block_opencast_ltiepisode');
        foreach ($existingepisodemodules as $episodemodule) {
            // Get the actual cm.
            $cm = get_coursemodule_from_id('lti', $episodemodule->cmid, $episodemodule->courseid);
            // Get the actual course object.
            $courseobject = get_course($episodemodule->courseid);
            // Get module information.
            list($unusedcm, $unusedcontext, $unusedmodule, $episodemoduledata, $unusedcw) =
                get_moduleinfo_data($cm, $courseobject);

            // Get the lti tool id set in the module.
            $cmtoolid = $episodemoduledata->typeid;

            // Extract actual module episode id.
            $cmepisodeid = str_replace('id=', '', trim($episodemoduledata->instructorcustomparameters));

            // In case, that the lti preconfigured tool is not available for episodes, then we remove the record right away.
            if (empty($episodetoolids[$cmtoolid])) {
                $DB->delete_records('block_opencast_ltiepisode', ['id' => $episodemodule->id]);
                $updatedmodulesnum++;
                continue;
            }

            // Now, we need to get the opencast instance set for the module.
            $cmocinstance = $episodetoolids[$cmtoolid];
            // If there is any changes we need to update the record.
            if ($episodemodule->episodeuuid !== $cmepisodeid || intval($episodemodule->ocinstanceid) !== intval($cmocinstance)) {
                $episodemodule->episodeuuid = strlen($cmepisodeid) > 36 ? 'invalid-id' : $cmepisodeid;
                $episodemodule->ocinstanceid = $cmocinstance;
                $DB->update_record('block_opencast_ltiepisode', $episodemodule);
                $updatedmodulesnum++;
            }
        }

        // Opencast lti series records.
        $existingseriesmodules = $DB->get_records('block_opencast_ltimodule');
        foreach ($existingseriesmodules as $seriesmodule) {
            // Get the actual cm.
            $cm = get_coursemodule_from_id('lti', $seriesmodule->cmid, $seriesmodule->courseid);
            // Get the actual course object.
            $courseobject = get_course($seriesmodule->courseid);
            // Get module information.
            list($unusedcm, $unusedcontext, $unusedmodule, $seriesmoduledata, $unusedcw) =
                get_moduleinfo_data($cm, $courseobject);

            // Get the lti tool id set in the module.
            $cmtoolid = $seriesmoduledata->typeid;

            // Extract actual module series id.
            $cmseriesid = str_replace('series=', '', trim($seriesmoduledata->instructorcustomparameters));

            // In case, that the lti preconfigured tool is not available for series, then we remove the record right away.
            if (empty($seriestoolids[$cmtoolid])) {
                $DB->delete_records('block_opencast_ltimodule', ['id' => $seriesmodule->id]);
                $updatedmodulesnum++;
                continue;
            }

            // Now, we need to get the opencast instance set for the module.
            $cmocinstance = $seriestoolids[$cmtoolid];

            // If there is any changes we need to update the record.
            if ($seriesmodule->seriesid !== $cmseriesid || intval($seriesmodule->ocinstanceid) !== intval($cmocinstance)) {
                $seriesmodule->seriesid = strlen($cmseriesid) > 36 ? 'invalid-id' : $cmseriesid;
                $seriesmodule->ocinstanceid = $cmocinstance;
                $DB->update_record('block_opencast_ltimodule', $seriesmodule);
                $updatedmodulesnum++;
            }
        }

        // Finally, we return the number of updated records.
        return $updatedmodulesnum;
    }

    /**
     * Gets the manually added course lti modules and sync them with the existing opencast lti module entries.
     * It will loop through all the moodle lti modules and check if they are using the preconfigured opencast lti tools.
     * The concept is to get all preconfigured opencast lti tools based on all opencast instances and then make a sql to check which
     * course module is there that uses any of the preconfigured opencast lti tools and its record does not exists in the entries
     * of both block_opencast_ltiepisode and block_opencast_ltimodule tables.
     * This function is intended to be called by the \block_opencast\task\cleanup_lti_module_cron scheduled task.
     *
     * @return int The number of unrecorded lti modules.
     */
    public static function record_manually_added_lti_modules() {
        global $DB;
        // Get all available opencast instances to get all the configured tool ids.
        $ocinstances = settings_api::get_ocinstances();

        // Getting preconfigured lti tools.
        $toolids = [];
        foreach ($ocinstances as $ocinstance) {
            $isaddltiepisodeenabled = get_config('block_opencast', 'addltiepisodeenabled_' . $ocinstance->id);
            if (!empty($isaddltiepisodeenabled)) {
                $episodetoolid = self::get_preconfigured_tool_for_episode($ocinstance->id);
                if (!empty($episodetoolid) && !in_array($episodetoolid, $toolids)) {
                    $toolids[] = ['id' => $episodetoolid, 'ocinstanceid' => $ocinstance->id];
                }
            }
            $isaddltienabled = get_config('block_opencast', 'addltienabled_' . $ocinstance->id);
            if (!empty($isaddltienabled)) {
                $seriestoolid = self::get_preconfigured_tool_for_series($ocinstance->id);
                if (!empty($seriestoolid) && !in_array($seriestoolid, $toolids)) {
                    $toolids[] = ['id' => $seriestoolid, 'ocinstanceid' => $ocinstance->id];
                }
            }
        }

        // Escaping this step when there is no tool id to work with.
        if (empty($toolids)) {
            return 0;
        }

        // Getting all the opencast entries of both episode and series lti modules.
        $existingcms = [];
        $existingepisodecms = $DB->get_fieldset_sql('SELECT cmid FROM {block_opencast_ltiepisode}');
        $existingseriescms = $DB->get_fieldset_sql('SELECT cmid FROM {block_opencast_ltimodule}');
        $existingcms = array_merge($existingepisodecms, $existingseriescms);

        // Get the id of the installed LTI plugin.
        $pluginid = $DB->get_field('modules', 'id', ['name' => 'lti']);

        // Preparing the sql and params to get the manually added opencast LTI modules in course, which should be also recorded.
        $insqlcms = '';
        $inparamscms = [];
        if (!empty($existingcms)) {
            list($insqlcms, $inparamscms) = $DB->get_in_or_equal($existingcms);
        }
        list($insqltoolids, $inparamstoolids) = $DB->get_in_or_equal(array_column($toolids, 'id'));

        $params = [$pluginid];
        $params = array_merge($params, $inparamstoolids, $inparamscms);
        $sql = 'SELECT cm.id, cm.course, cm.module, l.instructorcustomparameters, l.typeid FROM {course_modules} cm' .
            ' JOIN {lti} l ON cm.instance = l.id' .
            ' WHERE cm.module = ? ' .
            ' AND l.typeid ' . $insqltoolids;
        if (!empty($insqlcms)) {
            $sql .= ' AND cm.id ' . (count($inparamscms) > 1 ? 'NOT ' : '!') . $insqlcms;
        }
        $sql .= ' ORDER BY cm.added ASC';
        $unrecordedmodules = $DB->get_records_sql($sql, $params);

        // When there are manually added modules, we loop through them to make a record entry in respective DB tables.
        foreach ($unrecordedmodules as $unrecordedmodule) {
            $customparam = trim($unrecordedmodule->instructorcustomparameters);
            $toolid = $unrecordedmodule->typeid;
            // Extract the ocinstance from the toolids array.
            $ocinstanceid = $toolids[array_search($toolid, array_column($toolids, 'id'))]['ocinstanceid'];

            // Get an apibridge instance based on the ocinstance.
            $apibridge = apibridge::get_instance($ocinstanceid);
            if (empty($apibridge)) {
                continue;
            }

            // First, we get all the series in the course.
            $courseseries = $apibridge->get_course_series($unrecordedmodule->course);
            // We check if the customtool param of this module contains any of the course series ids.
            $targeteditem = array_filter($courseseries, function ($seriesobj) use ($customparam) {
                return (strpos($customparam, $seriesobj->series) !== false);
            });
            $isepisode = false;
            // If it is empty, that means the module is an episode LTI module.
            if (empty($targeteditem)) {
                $isepisode = true;
                // Then, we get all the opencast videos in the course, to determine if the module is an episode LTI module.
                $coursevideos = $apibridge->get_course_videos($unrecordedmodule->course);
                if ($coursevideos->error === 0 && !empty($coursevideos->videos)) {
                    // We check if the customtool param of this module contains any of the course episode ids.
                    $targeteditem = array_filter($coursevideos->videos, function ($episodeobj) use ($customparam) {
                        return (strpos($customparam, $episodeobj->identifier) !== false);
                    });
                }
            }

            // After checking against both course opencast videos and episodes, the item is now more likely to have value.
            if (!empty($targeteditem)) {
                // If so, we will insert a new record to the targeted table.
                $item = reset($targeteditem);
            } else {
                // If we hit here, the module has faulty information, therefore we still need to record it, in order to clean it up.
                // Initializing the item.
                $item = new stdClass();
                // We decide if the module is episode or series, based on the custom parameter.
                $isepisode = strpos($customparam, 'id=') !== false ? true : false;
                if ($isepisode) {
                    $episodeidentifier = str_replace('id=', '', $customparam);
                    if (strlen($episodeidentifier) > 36) {
                        $episodeidentifier = 'invalid-id';
                    }
                    $item->identifier = !empty($episodeidentifier) ? $episodeidentifier : 'undefined';
                } else {
                    $seriesid = str_replace('series=', '', $customparam);
                    if (strlen($seriesid) > 36) {
                        $seriesid = 'invalid-id';
                    }
                    $item->series = !empty($seriesid) ? $seriesid : 'undefined';
                }
            }

            $record = new stdClass();
            $record->cmid = $unrecordedmodule->id;
            $record->ocinstanceid = $ocinstanceid;
            $record->courseid = $unrecordedmodule->course;
            // If it is episode LTI Module.
            if ($isepisode) {
                $record->episodeuuid = $item->identifier;
                $DB->insert_record('block_opencast_ltiepisode', $record);
            } else {
                // If it is series LTI Module.
                $record->seriesid = $item->series;
                $DB->insert_record('block_opencast_ltimodule', $record);
            }
        }
        // Finally, we return the number of unrecorded modules.
        return count($unrecordedmodules);
    }

    /**
     * Helper function to clean up the LTI module entries, which are not valid anymore.
     * The concept is, to loop through the lti module entries and cleans up abandoned or invalid records.
     * The validation is applied to course, module, opencast instance and opencast series or episode.
     * Targeted tables are: {block_opencast_ltiepisode} and {block_opencast_ltimodule}
     * This function is intended to be called by the \block_opencast\task\cleanup_lti_module_cron scheduled task.
     *
     * @return int number of deleted modules.
     */
    public static function cleanup_lti_module_entries() {
        global $DB;

        $deletedrecordsnum = 0;
        // Episode modules.
        $targettable = 'block_opencast_ltiepisode';
        $allepisodemodules = $DB->get_records($targettable);
        foreach ($allepisodemodules as $episodemodule) {
            $isvalid = true;
            // Check if the setting is enabled at all.
            $isaddltiepisodeenabled = get_config('block_opencast', 'addltiepisodeenabled_' . $episodemodule->ocinstanceid);
            if (empty($isaddltiepisodeenabled)) {
                // We shutdown the cleanup process, to avoid unwanted behaviors.
                continue;
            }
            // Check if course is there.
            if (!self::check_course($episodemodule->courseid)) {
                $isvalid = false;
            }
            // Check if module exists.
            if (!self::check_module($episodemodule->cmid, $episodemodule->courseid)) {
                $isvalid = false;
            }
            // Check if ocinstance is there.
            if (!self::check_opencast_config($episodemodule->ocinstanceid)) {
                $isvalid = false;
            }
            // Check if episode exists in Opencast.
            if (!self::check_opencast_episode($episodemodule->episodeuuid, $episodemodule->ocinstanceid)) {
                $isvalid = false;
            }
            // If the lti module is faulty.
            if (!$isvalid) {
                try {
                    // We delete the record from the database.
                    $DB->delete_records($targettable, ['id' => $episodemodule->id]);
                    // Delete the module.
                    course_delete_module($episodemodule->cmid);
                    // Increament the num to return as an info.
                    $deletedrecordsnum++;
                } catch (moodle_exception $e) {
                    throw new moodle_exception(get_string('processltimodulecleanup_error', 'block_opencast', $e->getMessage()));
                }
            }
        }

        // Series modules.
        $targettable = 'block_opencast_ltimodule';
        $allseriesmodules = $DB->get_records($targettable);
        foreach ($allseriesmodules as $seriesmodule) {
            $isvalid = true;
            // Check if the setting is enabled at all.
            $isaddltienabled = get_config('block_opencast', 'addltienabled_' . $seriesmodule->ocinstanceid);
            if (empty($isaddltienabled)) {
                // We shutdown the cleanup process, to avoid unwanted behaviors.
                continue;
            }
            // Check if course is there.
            if (!self::check_course($seriesmodule->courseid)) {
                $isvalid = false;
            }
            // Check if module exists.
            if (!self::check_module($seriesmodule->cmid, $seriesmodule->courseid)) {
                $isvalid = false;
            }
            // Check if ocinstance is there.
            if (!self::check_opencast_config($seriesmodule->ocinstanceid)) {
                $isvalid = false;
            }
            // Check if episode exists in Opencast.
            if (!self::check_opencast_series($seriesmodule->seriesid, $seriesmodule->ocinstanceid)) {
                $isvalid = false;
            }
            // If the lti module is faulty.
            if (!$isvalid) {
                try {
                    // We delete the record from the database.
                    $DB->delete_records($targettable, ['id' => $seriesmodule->id]);
                    // Delete the module.
                    course_delete_module($seriesmodule->cmid);
                    // Increament the num to return as an info.
                    $deletedrecordsnum++;
                } catch (moodle_exception $e) {
                    throw new moodle_exception(get_string('processltimodulecleanup_error', 'block_opencast', $e->getMessage()));
                }
            }
        }
        // Finally, we return the number of deleted records.
        return $deletedrecordsnum;
    }

    /**
     * Checks if the course is valid and running.
     *
     * @param int $courseid course id
     * @return boolean
     */
    private static function check_course($courseid) {
        global $DB;
        if (empty($courseid)) {
            return false;
        }
        // Get the course.
        $course = $DB->get_record('course', ['id' => $courseid]);
        return !empty($course);
    }

    /**
     * Checks if the opencast configuration is valid. THis applies to opencast instance as well as the api configurations.
     *
     * @param int $ocinstanceid opencast instance id
     * @return boolean
     */
    private static function check_opencast_config($ocinstanceid) {
        if (empty($ocinstanceid)) {
            return false;
        }
        $isvalid = true;
        try {
            $ocinstance = settings_api::get_ocinstance($ocinstanceid);
            if (!empty($ocinstance)) {
                $apibridge = apibridge::get_instance($ocinstanceid);
                $apibridgeworking = $apibridge->check_api_configuration();
                if (!$apibridgeworking) {
                    $isvalid = false;
                }
            } else {
                $isvalid = false;
            }
        } catch (moodle_exception $e) {
            $isvalid = false;
        }
        return $isvalid;
    }

    /**
     * Checks if the course module is valid.
     *
     * @param int $cmid course module id
     * @param int $courseid course id
     * @return boolean
     */
    private static function check_module($cmid, $courseid) {
        if (empty($cmid) || empty($courseid)) {
            return false;
        }
        $cm = get_coursemodule_from_id('lti', $cmid, $courseid);
        if ($cm == false || $cm->deletioninprogress == 1) {
            return false;
        }
        return true;
    }

    /**
     * Checks if the episode exists in opencast.
     *
     * @param string $identifier epidsode id
     * @param int $ocinstanceid opencast instance id
     * @return boolean
     */
    private static function check_opencast_episode($identifier, $ocinstanceid) {
        if (empty($identifier) || empty($ocinstanceid)) {
            return false;
        }
        $apibridge = apibridge::get_instance($ocinstanceid);
        $video = $apibridge->get_opencast_video($identifier);
        return !empty($video) && $video->error == 0;
    }

    /**
     * Checks if the series exists in opencast.
     *
     * @param string $identifier series id
     * @param int $ocinstanceid opencast instance id
     * @return boolean
     */
    private static function check_opencast_series($identifier, $ocinstanceid) {
        if (empty($identifier) || empty($ocinstanceid)) {
            return false;
        }
        $apibridge = apibridge::get_instance($ocinstanceid);
        $series = $apibridge->get_series_by_identifier($identifier);
        return !empty($series);
    }

    /**
     * Looks up for series LTI modules in a new (imported) course that has faulty (old) series id.
     * Repairs the faulty LTI module by replacing the new series id and inserts the series module in "block_opencast_ltimodule".
     * @see importvideosmanager::fix_imported_series_modules_in_new_course() After the restore is completed.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid New course id.
     * @param string $sourceseriesid Old series id.
     * @param string $newseriesid New series id.
     *
     * @return void
     */
    public static function fix_imported_series_modules_in_new_course(
        $ocinstanceid, $courseid, $sourceseriesid, $newseriesid) {
        global $CFG, $DB;
        // Require grade library. For an unknown reason, this is needed when updating the module.
        require_once($CFG->libdir . '/gradelib.php');

        // Get the id of the preconfigured tool.
        $toolid = self::get_preconfigured_tool_for_series($ocinstanceid);

        $sql = 'SELECT cm.id AS cmid FROM {lti} l ' .
            'JOIN {course_modules} cm ' .
            'ON l.id = cm.instance ' .
            'WHERE l.typeid = :toolid ' .
            'AND cm.course = :course ' .
            'AND ' . $DB->sql_like('l.instructorcustomparameters', ':sourceseriesid') .
            ' ORDER BY cm.added ASC';
        $params = ['toolid' => $toolid,
            'course' => $courseid,
            'sourceseriesid' => '%' . $DB->sql_like_escape($sourceseriesid) . '%', ];
        $seriesmodules = $DB->get_fieldset_sql($sql, $params);

        // If there are any existing series modules in this course.
        if (count($seriesmodules) > 0) {
            // Iterate over modules.
            foreach ($seriesmodules as $cmid) {
                $seriesmoduleobject = get_coursemodule_from_id('lti', $cmid, $courseid);
                if (!empty($seriesmoduleobject)) {
                    $courseobject = get_course($courseid);
                    list($unusedcm, $unusedcontext, $unusedmodule, $seriesmoduledata, $unusedcw) =
                        get_moduleinfo_data($seriesmoduleobject, $courseobject);

                    // Replace the series identifier in the module info with the new one.
                    $seriesmoduledata->instructorcustomparameters = 'series=' . $newseriesid;

                    // Update the series identifier within the series module.
                    update_module($seriesmoduledata);

                    // Insert the data into db to make it visible to the plugin as well.
                    $record = new stdClass();
                    $record->courseid = $courseid;
                    $record->cmid = $cmid;
                    $record->ocinstanceid = $ocinstanceid;
                    $record->seriesid = $newseriesid;
                    $DB->insert_record('block_opencast_ltimodule', $record);
                }
            }
        }
    }

    /**
     * Looks up for episode LTI modules in a new (imported) course that has faulty (old) event id.
     * Repairs the faulty LTI module by replacing the new event id and inserts the episode module in "block_opencast_ltiepisode".
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
        $ocinstanceid, $targetcourseid, $sourceeventid, $duplicatedeventid) {
        global $CFG, $DB;
        // Require course module library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // Require grade library. For an unknown reason, this is needed when updating the module.
        require_once($CFG->libdir . '/gradelib.php');

        // Get the id of the preconfigured tool.
        $toolid = self::get_preconfigured_tool_for_episode($ocinstanceid);

        // Get the LTI episode module(s) in the new course which point to the old event id.
        $sql = 'SELECT cm.id AS cmid FROM {lti} l ' .
            'JOIN {course_modules} cm ' .
            'ON l.id = cm.instance ' .
            'WHERE l.typeid = :toolid ' .
            'AND cm.course = :course ' .
            'AND ' . $DB->sql_like('l.instructorcustomparameters', ':sourceeventid');
        $params = ['toolid' => $toolid,
            'course' => $targetcourseid,
            'sourceeventid' => '%' . $sourceeventid . '%', ];
        $episodemodules = $DB->get_fieldset_sql($sql, $params);

        // If there are any existing episode modules in this course.
        if (count($episodemodules) > 0) {
            // Iterate over modules.
            foreach ($episodemodules as $cmid) {
                // Gather more information about this module so that we can update the module info in the end.
                $episodemoduleobject = get_coursemodule_from_id('lti', $cmid, $targetcourseid);
                $courseobject = get_course($targetcourseid);
                list($unusedcm, $unusedcontext, $unusedmodule, $episodemoduledata, $unusedcw) =
                    get_moduleinfo_data($episodemoduleobject, $courseobject);

                // Replace the episode identifier in the module info.
                $episodemoduledata->instructorcustomparameters = 'id=' . $duplicatedeventid;

                // Update the episode identifier within the episode module.
                update_module($episodemoduledata);

                // Remember this episode module id as the episode module of the course.
                $record = new stdClass();
                $record->courseid = $targetcourseid;
                $record->episodeuuid = $duplicatedeventid;
                $record->cmid = $cmid;
                $record->ocinstanceid = $ocinstanceid;
                $DB->insert_record('block_opencast_ltiepisode', $record);
            }
        }
    }
}
