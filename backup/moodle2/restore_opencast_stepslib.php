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
 * Define all the restore steps that will be used by the restore opencast block task.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

use block_opencast\local\event;
use block_opencast\local\notifications;

/**
 * Define all the restore steps that will be used by the restore opencast block task.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_opencast_block_structure_step extends restore_structure_step {

    private $backupeventids = [];
    private $missingeventids = [];
    private $seriesid = null;

    /**
     * Function that will return the structure to be processed by this restore_step.
     *
     * @return array of @restore_path_element elements
     */
    protected function define_structure() {

        // Check, target series.
        $courseid = $this->get_courseid();

        $apibridge = \block_opencast\local\apibridge::get_instance();
        // If seriesid does not exist, we do not skip restore task here,
        // because we want to collect all the events (see process event),
        // that should have been restored.
        $this->seriesid = $apibridge->get_stored_seriesid($courseid);

        $paths = array();
        $paths[] = new restore_path_element('event', '/block/opencast/events/event');

        return $paths;
    }

    /**
     * Process the backuped data.
     *
     * @param array $data the event identifier
     * @return void
     */
    public function process_event($data) {

        $data = (object) $data;

        // Collect eventids for notification.
        $this->backupeventids[] = $data->eventid;

        // Exit when there is no course series.
        if (!$this->seriesid) {
            return;
        }

        // Check, whether event exists on opencast server.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Only duplicate, when the event exists in opencast.
        if (!$event = $apibridge->get_already_existing_event([$data->eventid])) {
            $this->missingeventids[] = $data->eventid;
        } else {
            $courseid = $this->get_courseid();
            event::create_duplication_task($courseid, $this->seriesid, $data->eventid);
        }
    }

    /**
     * Send notifications after restore, to inform admins about errors.
     *
     * @return void
     */
    public function after_restore() {

        $courseid = $this->get_courseid();

        // None of the backupeventids are used for starting a workflow.
        if (!$this->seriesid) {
            notifications::notify_failed_course_series($courseid, $this->backupeventids);
            return;
        }

        // A course series is created, but some events are not found on opencast server.
        if ($this->missingeventids) {
            notifications::notify_missing_events($courseid, $this->missingeventids);
        }
    }

}
