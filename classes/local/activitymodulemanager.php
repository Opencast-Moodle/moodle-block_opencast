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

use mod_opencast\local\opencasttype;

defined('MOODLE_INTERNAL') || die();

/**
 * LTI module management for block_opencast.
 *
 * @package    block_opencast
 * @copyright  2021 Justus Dieckmann WWU, 2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activitymodulemanager
{

    /**
     * Helperfunction to get the status of the Opencast series feature.
     * This consists of a check if the feature is enabled by the admin.
     *
     * @return boolean
     */
    public static function is_enabled_and_working_for_series($ocinstanceid): bool {
        return get_config('block_opencast', 'addactivityenabled_' . $ocinstanceid) != false &&
            \core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
    }

    /**
     * Helperfunction to get the status of the Opencast episodes feature.
     * This consists of a check if the feature is enabled by the admin.
     *
     * @return boolean
     */
    public static function is_enabled_and_working_for_episodes($ocinstanceid) {
        return get_config('block_opencast', 'addactivityepisodeenabled_'.$ocinstanceid) != false &&
            \core_plugin_manager::instance()->get_plugin_info('mod_opencast') != null;
    }

    /**
     * Helperfunction to create the Opencast LTI series module in a course.
     *
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
    public static function create_module_for_series($courseid, $ocinstanceid, $title, $seriesid, $sectionid = 0, $introtext = '',
                                                    $introformat = FORMAT_HTML, $availability = null) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // If the title or the series is empty, something is wrong.
        if (empty($title) || empty($seriesid)) {
            return false;
        }

        // Get the id of the installed Opencast plugin.
        $pluginid = $DB->get_field('modules', 'id', array('name' => 'opencast'));

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
        $moduleinfo = self::build_activity_modinfo($pluginid, $ocinstanceid, $title, $sectionid, $seriesid, opencasttype::SERIES,
            $introtext, $introformat, $availability);

        // Add the Opencast Activity series module to the given course.
        // This does not check any capabilities to add modules to courses by purpose.
        \add_moduleinfo($moduleinfo, $course);

        return true;
    }

    /**
     * Helperfunction to create the Opencast LTI episode module in a course.
     *
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
    public static function create_module_for_episode($courseid, $ocinstanceid, $title, $episodeuuid, $sectionid = 0, $introtext = '',
                                                     $introformat = FORMAT_HTML, $availability = null) {
        global $CFG, $DB;

        // Require mod library.
        require_once($CFG->dirroot . '/course/modlib.php');

        // If the title or the episode is empty, something is wrong.
        if (empty($title) || empty($episodeuuid)) {
            return false;
        }

        // Get the id of the installed Opencast plugin.
        $pluginid = $DB->get_field('modules', 'id', array('name' => 'opencast'));

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
        $moduleinfo = self::build_activity_modinfo($pluginid, $ocinstanceid, $title, $sectionid, $episodeuuid, opencasttype::EPISODE,
            $introtext, $introformat, $availability);

        // Add the Opencast Activity episode module to the given course.
        // This does not check any capabilities to add modules to courses by purpose.
        \add_moduleinfo($moduleinfo, $course);

        return true;
    }

    /**
     * Helperfunction to create a modinfo class, holding the Opencast LTI module information.
     *
     * @param int $pluginid
     * @param string $title
     * @param int $sectionid
     * @param string $opencastid
     * @param int $type opencasttype
     * @param string $introtext
     * @param int $introformat
     * @param string $availability
     *
     * @return object
     */
    public static function build_activity_modinfo($pluginid, $ocinstanceid, $title, $sectionid, $opencastid, $type,
                                                  $introtext = '', $introformat = FORMAT_HTML, $availability = null) {
        global $CFG;

        // Create standard class object.
        $moduleinfo = new \stdClass();

        // Populate the modinfo object with standard parameters.
        $moduleinfo->modulename = 'opencast';
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
        $moduleinfo->completion = $CFG->completiondefault;

        // Populate the modinfo object with opencast activity specific parameters.
        $moduleinfo->type = $type;
        $moduleinfo->opencastid = $opencastid;
        $moduleinfo->ocinstanceid = $ocinstanceid;

        // Return modinfo.
        return $moduleinfo;
    }

    /**
     * Helperfunction to get the Opencast series module of a course.
     * This includes a sanity check if the stored series module still exists.
     *
     * @param int $courseid
     *
     * @return int|boolean
     */
    // todo check
    public static function get_module_for_series($ocinstanceid, $courseid, $seriesid) {
        global $DB;

        // TODO hier demnächst oc instance id nötig -> ensure backwawrds compatibility
        // Get the Opencast Activity series module id.
        $instance = $DB->get_field('opencast', 'id', array('course' => $courseid,
            'type' => opencasttype::SERIES, 'opencastid' => $seriesid), IGNORE_MULTIPLE);

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
     *
     * @return int|boolean
     */
    public static function get_module_for_episode($courseid, $episodeuuid) {
        global $DB;

        // Get the Opencast Activity series module id.
        $instance = $DB->get_field('opencast', 'id', array('course' => $courseid,
            'opencastid' => $episodeuuid, 'type' => opencasttype::EPISODE), IGNORE_MULTIPLE);

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
     * @return string
     */
    public static function get_default_title_for_series($ocinstanceid) {
        // Get the default title from the admin settings.
        $defaulttitle = get_config('block_opencast', 'addactivitydefaulttitle_'.$ocinstanceid);

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
     * @param string $episodeuuid
     *
     * @return string
     */
    public static function get_default_title_for_episode($ocinstanceid, $episodeuuid) {
        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

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
     * @param string $episodeuuid
     *
     * @return string
     */
    public static function get_default_intro_for_episode($ocinstanceid, $episodeuuid) {
        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

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
            return array();
        }

        // Get list of sections.
        $coursemodinfo = \course_modinfo::instance($courseid);
        $sections = $coursemodinfo->get_section_info_all();

        // Extract section titles and build section menu.
        $sectionmenu = array();
        foreach ($sections as $id => $section) {
            $sectionmenu[$id] = get_section_name($courseid, $id);
        }

        // Finally, return the course section array.
        return $sectionmenu;
    }
}
