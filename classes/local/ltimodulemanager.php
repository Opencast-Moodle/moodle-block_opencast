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

defined('MOODLE_INTERNAL') || die();

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
        require_once($CFG->dirroot.'/mod/lti/locallib.php');

        // Get configured tools and filter them for tools which are configured by the admin.
        $types = \lti_filter_get_types(get_site()->id);
        $configuredtools = lti_filter_tool_types($types, LTI_TOOL_STATE_CONFIGURED);

        // Initialize array of tool to be returned.
        $tools = array();

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
     * @return int|boolean
     */
    public static function get_preconfigured_tool_for_series() {
        // Get the preconfigured LTI tool to be used.
        $toolid = get_config('block_opencast', 'addltipreconfiguredtool');

        // Get the list of available preconfigured LTI tools.
        $tools = self::get_preconfigured_tools();

        // If the preconfigured LTI tool to be used is not in the list of available tools, something is wrong.
        if (!array_key_exists($toolid, $tools)) {
            // Reset the plugin config.
            set_config('block_opencast', null, 'addltipreconfiguredtool');

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
     * @return int|boolean
     */
    public static function get_preconfigured_tool_for_episode() {
        // Get the preconfigured LTI tool to be used.
        $toolid = get_config('block_opencast', 'addltiepisodepreconfiguredtool');

        // Get the list of available preconfigured LTI tools.
        $tools = self::get_preconfigured_tools();

        // If the preconfigured LTI tool to be used is not in the list of available tools, something is wrong.
        if (!array_key_exists($toolid, $tools)) {
            // Reset the plugin config.
            set_config('block_opencast', null, 'addltiepisodepreconfiguredtool');

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
     * @return int|boolean
     */
    public static function is_enabled_and_working_for_series() {
        // Get the status of the feature.
        $config = get_config('block_opencast', 'addltienabled');

        // If the setting is false, then the feature is not working.
        if ($config == false) {
            // Inform the caller.
            return false;
        }

        // Get the preconfigured tool.
        $tool = self::get_preconfigured_tool_for_series();

        // If the tool is empty, then the feature is not working.
        if ($tool == false) {
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
     * @return int|boolean
     */
    public static function is_enabled_and_working_for_episodes() {
        // Remember the status for subsequent calls.
        static $enabledandworking = null;

        // If we know the status already, inform the caller directly.
        if ($enabledandworking !== null) {
            return $enabledandworking;
        }

        // If we don't know the status yet, check the status of the feature.
        if ($enabledandworking === null) {
            // Get the status of the feature.
            $config = get_config('block_opencast', 'addltiepisodeenabled');

            // If the setting is false, then the feature is not working.
            if ($config == false) {
                // Remember the status.
                $enabledandworking = false;
            }
        }

        // If we don't know the status yet, check the preconfigured tool.
        if ($enabledandworking === null) {
            // Get the preconfigured tool.
            $tool = self::get_preconfigured_tool_for_episode();

            // If the tool is empty, then the feature is not working.
            if ($tool == false) {
                // Inform the caller.
                $enabledandworking = false;
            }
        }

        // If everything was fine up to now, we should be sure that the feature is working.
        if ($enabledandworking !== false) {
            $enabledandworking = true;
        }

        // Inform the caller.
        return $enabledandworking;
    }

    /**
     * Helperfunction to create the Opencast LTI series module in a course.
     *
     * @param int $courseid
     * @param string $title
     * @param string $seriesid
     * @param int $sectionid
     *
     * @return boolean
     */
    public static function create_module_for_series($courseid, $title, $seriesid, $sectionid = 0) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot.'/course/modlib.php');

        // If the title or the series is empty, something is wrong.
        if (empty($title) || empty($seriesid)) {
            return false;
        }

        // Get the id of the installed LTI plugin.
        $pluginid = $DB->get_field('modules', 'id', array('name' => 'lti'));

        // If the LTI plugin is not found, something is wrong.
        if (!$pluginid) {
            return false;
        }

        // Get the course.
        $course = $DB->get_record('course', array('id' => $courseid));

        // If the course is not found, something is wrong.
        if (!$course) {
            return false;
        }

        // Get the preconfigured LTI tool to be used.
        $toolid = self::get_preconfigured_tool_for_series();

        // If the preconfigured LTI tool to be used is not configured correctly, something is wrong.
        if ($toolid == false) {
            return false;
        }

        // Create an LTI modinfo object.
        $moduleinfo = self::build_lti_modinfo($pluginid, $title, $sectionid, $toolid, 'series='.$seriesid);

        // Add the LTI series module to the given course (this does not check any capabilities to add modules to courses by purpose).
        $modulecreated = \add_moduleinfo($moduleinfo, $course);

        // Remember the module id.
        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->cmid = $modulecreated->coursemodule;
        $DB->insert_record('block_opencast_ltimodule', $record);

        return true;
    }

    /**
     * Helperfunction to create the Opencast LTI episode module in a course.
     *
     * @param int $courseid
     * @param string $title
     * @param string $episodeuuid
     * @param int $sectionid
     *
     * @return boolean
     */
    public static function create_module_for_episode($courseid, $title, $episodeuuid, $sectionid = 0) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot.'/course/modlib.php');

        // If the title or the episode is empty, something is wrong.
        if (empty($title) || empty($episodeuuid)) {
            return false;
        }

        // Get the id of the installed LTI plugin.
        $pluginid = $DB->get_field('modules', 'id', array('name' => 'lti'));

        // If the LTI plugin is not found, something is wrong.
        if (!$pluginid) {
            return false;
        }

        // Get the course.
        $course = $DB->get_record('course', array('id' => $courseid));

        // If the course is not found, something is wrong.
        if (!$course) {
            return false;
        }

        // Get the preconfigured LTI tool to be used.
        $toolid = self::get_preconfigured_tool_for_episode();

        // If the preconfigured LTI tool to be used is not configured correctly, something is wrong.
        if ($toolid == false) {
            return false;
        }

        // Create an LTI modinfo object.
        $moduleinfo = self::build_lti_modinfo($pluginid, $title, $sectionid, $toolid, 'id='.$episodeuuid);

        // Add the LTI episode module to the given course (this does not check any capabilities to add modules to courses by purpose).
        $modulecreated = \add_moduleinfo($moduleinfo, $course);

        // Remember the module id.
        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->episodeuuid = $episodeuuid;
        $record->cmid = $modulecreated->coursemodule;
        $DB->insert_record('block_opencast_ltiepisode', $record);

        return true;
    }

    /**
     * Helperfunction to create a modinfo class, holding the Opencast LTI module information.
     *
     * @param int $pluginid
     * @param string $title
     * @param int $sectionid
     * @param int $toolid
     * @param string $instructorcustomparameters
     *
     * @return object
     */
    public static function build_lti_modinfo($pluginid, $title, $sectionid, $toolid, $instructorcustomparameters) {
        // Create standard class object.
        $moduleinfo = new \stdClass();

        // Populate the modinfo object with standard parameters.
        $moduleinfo->modulename = 'lti';
        $moduleinfo->module = $pluginid;

        $moduleinfo->name = $title;
        $moduleinfo->intro = '';
        $moduleinfo->introformat = FORMAT_HTML;

        $moduleinfo->section = $sectionid;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = NOGROUPS;
        $moduleinfo->groupingid = 0;
        $moduleinfo->availability = null;
        $moduleinfo->completion = 0;

        // Populate the modinfo object with LTI specific parameters.
        $moduleinfo->typeid = $toolid;
        $moduleinfo->showtitlelaunch = true;
        $moduleinfo->instructorcustomparameters = $instructorcustomparameters;

        // Return modinfo.
        return $moduleinfo;
    }

    /**
     * Helperfunction to get the Opencast LTI series module of a course.
     * This includes a sanity check if the stored LTI series module still exists.
     *
     * @param int $courseid
     *
     * @return int|boolean
     */
    public static function get_module_for_series($courseid) {
        global $DB;

        // Get the LTI series module id.
        $moduleid = $DB->get_field('block_opencast_ltimodule', 'cmid', array('courseid' => $courseid));

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
                $DB->delete_records('block_opencast_ltimodule', array('courseid' => $courseid));

                // Inform the caller.
                return false;
            }
        }

        // Return the LTI module id.
        return $moduleid;
    }

    /**
     * Helperfunction to get the Opencast LTI episode modules of a course.
     * This includes a sanity check if the stored LTI episode modules still exists.
     *
     * @param int $courseid
     *
     * @return array
     */
    public static function get_modules_for_episodes($courseid) {
        global $DB;

        // Get the LTI episode module ids.
        $modules = $DB->get_records_menu('block_opencast_ltiepisode', array('courseid' => $courseid), '', 'episodeuuid, cmid');

        // Return the LTI module ids.
        return $modules;
    }

    /**
     * Helperfunction to pick the Opencast LTI episode module of a particular episode from a given associative array of modules.
     *
     * @param array $modules
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return int|boolean
     */
    public static function pick_module_for_episode($modules, $courseid, $episodeuuid) {
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
                $DB->delete_records('block_opencast_ltiepisode', array('episodeuuid' => $episodeuuid));

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
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return int|boolean
     */
    public static function get_module_for_episode($courseid, $episodeuuid) {
        // Get the existing modules of the course.
        $modules = self::get_modules_for_episodes($courseid);

        // Pick the module for the given episode.
        $moduleid = self::pick_module_for_episode($modules, $courseid, $episodeuuid);

        // Return the LTI module id.
        return $moduleid;
    }

    /**
     * Helperfunction to get the default Opencast LTI series module title.
     * This includes a fallback for the case that the admin has set it to an empty string.
     *
     * @return string
     */
    public static function get_default_title_for_series() {
        // Get the default title from the admin settings.
        $defaulttitle = get_config('block_opencast', 'addltidefaulttitle');

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
     * @param string $episodeuuid
     *
     * @return string
     */
    public static function get_default_title_for_episode($episodeuuid) {
        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

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
}
