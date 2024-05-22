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
 * @author     Andreas Wagner
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\apibridge;
use tool_opencast\local\settings_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/backup/moodle2/backup_stepslib.php');

/**
 * Define all the backup steps that will be used by the backup_opencast_block_task
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_opencast_block_structure_step extends backup_block_structure_step {

    /**
     * Defines the structure of the backup file.
     * @return backup_nested_element
     * @throws base_element_struct_exception
     * @throws base_step_exception
     * @throws dml_exception
     */

    /** @var array data The information about backup structure */
    private $data = [];

    /**
     * Returns data.
     * @return array data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Sets data.
     * @see backup_opencast_block_task::define_my_steps() Usage.
     * @param array $data the information about backup structure.
     */
    public function set_data($data) {
        $this->data = $data;
    }

    /**
     * Defines the structure of the block backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Extracting ocinstanceid from the structure.
        $ocinstanceid = intval(ltrim($this->get_name(), "opencast_structure_"));

        // Extracting selected series and events from the data structure.
        $selectedseriestobackup = [];
        $selectedvideostobackup = [];
        if (!empty($this->data)) {
            foreach ($this->data as $seriesid => $eventslist) {
                if (!empty($eventslist)) {
                    $selectedseriestobackup[] = $seriesid;
                    $selectedvideostobackup = array_merge($eventslist, $selectedvideostobackup);
                }
            }
        }
        // Root.
        $opencast = new backup_nested_element('opencast');

        // Site information.
        $site = new backup_nested_element('site', [], ['apiurl', 'identifier', 'ocinstanceid']);
        $opencast->add_child($site);

        $apiurl = settings_api::get_apiurl($ocinstanceid);
        $sitedata = (object)[
            'ocinstanceid' => $ocinstanceid,
            'apiurl' => $apiurl,
        ];
        $site->set_source_array([$sitedata]);

        // Events information.
        $events = new backup_nested_element('events');
        $event = new backup_nested_element('event', [], ['eventid']);
        $events->add_child($event);
        $opencast->add_child($events);

        // Check, whether there are course videos available.
        $apibridge = apibridge::get_instance($ocinstanceid);

        $courseid = $this->get_courseid();
        $coursevideos = $apibridge->get_course_videos_for_backup($courseid);

        $list = [];
        // Add course videos.
        foreach ($coursevideos as $video) {
            // Check if they are selected.
            if (in_array($video->identifier, $selectedvideostobackup)) {
                $list[] = (object)['eventid' => $video->identifier];
            }
        }

        // Define sources.
        $event->set_source_array($list);

        // Import information.
        $import = new backup_nested_element('import', [], ['sourcecourseid']);
        $serieselement = new backup_nested_element('series', [], ['seriesid']);
        $import->add_child($serieselement);
        $opencast->add_child($import);

        // Get the stored seriesid for this course.
        $courseseries = $apibridge->get_course_series($courseid);

        $list = [];
        foreach ($courseseries as $series) {
            // Check if it is selected.
            if (in_array($series->series, $selectedseriestobackup)) {
                $list[] = (object)['seriesid' => $series->series];
            }
        }
        $serieselement->set_source_array($list);

        $importdata = (object)[
            'sourcecourseid' => $courseid,
        ];

        $import->set_source_array([$importdata]);

        // Return the root element ($opencast), wrapped into standard block structure.
        return $this->prepare_block_structure($opencast);
    }
}
