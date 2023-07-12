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
 * Class for deleting videos of deleted courses.
 *
 * @package    block_opencast
 * @copyright  2023 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use core\event\course_deleted;
use tool_opencast\local\settings_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for deleting videos of deleted courses.
 *
 * @package    block_opencast
 * @copyright  2023 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_cleanup {

    /**
     * Callback for the course deletion observer.
     * @param course_deleted $event The course deletion event.
     */
    public static function course_deletion_observed($event) {
        global $DB;

        $courseid = $event->get_data()['courseid'];
        foreach (settings_api::get_ocinstances() as $ocinstance) {
            $apibridge = apibridge::get_instance($ocinstance->id);
            foreach ($apibridge->get_course_series($courseid) as $courseseries) {
                // Course was already deleted, so if there is any mapping with existing courses left, don't delete!
                if ($DB->record_exists_sql('SELECT c.id FROM {tool_opencast_series} s
                        JOIN {course} c ON s.courseid = c.id
                        WHERE series = :series AND ocinstanceid = :instance',
                        ['series' => $courseseries->series, 'instance' => $ocinstance->id])) {
                    continue;
                }
                $seriesvideos = $apibridge->get_series_videos($courseseries->series);
                if ($seriesvideos->error != 0) {
                    mtrace("Could not retrieve series $courseseries->series.");
                    continue;
                }
                foreach ($seriesvideos->videos as $video) {
                    $response = $apibridge->api->opencastapi->eventsApi->delete($video->identifier);
                    if (in_array($response['code'], [202, 204, 404])) {
                        continue;
                    }
                    mtrace("Error deleting event $video->identifier.");
                }
            }
        }
    }
}
