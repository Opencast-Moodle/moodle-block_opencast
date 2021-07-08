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
 * Define all the backup steps that will be used by the backup_opencast_block_task
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_opencast\local\settings_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/backup/moodle2/backup_stepslib.php');

/**
 * Define all the backup steps that will be used by the backup_opencast_block_task
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_opencast_block_structure_step extends backup_block_structure_step {

    protected function define_structure() {
        $ocinstanceid = intval(ltrim($this->get_name(), "opencast_structure_"));

        // Root.
        $opencast = new backup_nested_element('opencast');

        // Site information.
        $site = new backup_nested_element('site', array(), array('apiurl', 'identifier', 'ocinstanceid'));
        $opencast->add_child($site);

        $apiurl = settings_api::get_apiurl($ocinstanceid);
        $sitedata = (object) [
                'ocinstanceid' => $ocinstanceid,
                'apiurl' => $apiurl
        ];
        $site->set_source_array([$sitedata]);

        // Events information.
        $events = new backup_nested_element('events');
        $event = new backup_nested_element('event', array(), array('eventid'));
        $events->add_child($event);
        $opencast->add_child($events);

        // Check, whether there are course videos available.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        $courseid = $this->get_courseid();
        $coursevideos = $apibridge->get_course_videos_for_backup($courseid);

        $list = [];
        // Add course videos.
        foreach ($coursevideos as $video) {
            $list[] = (object) ['eventid' => $video->identifier];
        }

        // Define sources.
        $event->set_source_array($list);

        // Import information.
        $import = new backup_nested_element('import', array(), array('seriesid', 'sourcecourseid'));
        $opencast->add_child($import);

        // Get the stored seriesid for this course.
        $seriesid = $apibridge->get_stored_seriesid($courseid);

        $importdata = (object) [
            'seriesid' => $seriesid,
            'sourcecourseid' => $courseid
        ];

        $import->set_source_array([$importdata]);

        // Return the root element ($opencast), wrapped into standard block structure.
        return $this->prepare_block_structure($opencast);
    }
}
