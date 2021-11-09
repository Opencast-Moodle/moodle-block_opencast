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
class restore_opencast_block_structure_step extends restore_structure_step
{

    private $backupeventids = [];
    private $missingeventids = [];
    private $series = [];
    private $aclchanged = [];
    private $ocinstanceid;
    private $importmode;

    /**
     * Function that will return the structure to be processed by this restore_step.
     *
     * @return array of @restore_path_element elements
     */
    protected function define_structure() {
        global $USER;
        $ocinstanceid = intval(ltrim($this->get_name(), "opencast_structure_"));
        $this->ocinstanceid = $ocinstanceid;

        // Check, target series.
        $courseid = $this->get_courseid();

        $paths = array();

        // Get apibridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Get the import mode to decide the way of importing opencast videos.
        $importmode = get_config('block_opencast', 'importmode_' . $ocinstanceid);
        $this->importmode = $importmode;

        // If ACL Change is the mode.
        if ($importmode == 'acl') {
            // We process the rest in the process import.
            $paths[] = new restore_path_element('import', '/block/opencast/import');
            $paths[] = new restore_path_element('series', '/block/opencast/import/series');
        } else if ($importmode == 'duplication') {
            // In case Duplicating Events is the mode.

            // Get series id.
            $seriesid = $apibridge->get_stored_seriesid($courseid, true, $USER->id);

            // If seriesid does not exist, we create one.
            if (!$seriesid) {
                // Make sure to create using another method.
                $seriesid = $apibridge->create_course_series($courseid, null, $USER->id);
            }
            $this->series[] = $seriesid;

            $paths[] = new restore_path_element('event', '/block/opencast/events/event');
        }

        return $paths;
    }

    /**
     * Process the backuped data.
     *
     * @param array $data the event identifier
     * @return void
     */
    public function process_event($data) {
        $data = (object)$data;

        // Collect eventids for notification.
        $this->backupeventids[] = $data->eventid;

        // Exit when there is no course series.
        if (!$this->series) {
            return;
        }

        // Check, whether event exists on opencast server.
        $apibridge = \block_opencast\local\apibridge::get_instance($this->ocinstanceid);

        // Only duplicate, when the event exists in opencast.
        if (!$apibridge->get_already_existing_event([$data->eventid])) {
            $this->missingeventids[] = $data->eventid;
        } else {
            $courseid = $this->get_courseid();
            event::create_duplication_task($this->ocinstanceid, $courseid, $this->series[0], $data->eventid);
        }
    }

    public function process_series($data) {
        global $USER;

        $data = (object)$data;

        // Check, target series.
        $courseid = $this->get_courseid();

        // Get apibridge instance, to ensure series validity and edit series mapping.
        $apibridge = \block_opencast\local\apibridge::get_instance($this->ocinstanceid);

        // Exit when there is no original series, no course course id and the original seriesid is not valid.
        // Also exit when the course by any chance wanted to restore itself.
        if (!$data->seriesid && !$data->sourcecourseid &&
            $apibridge->ensure_series_is_valid($data->seriesid) && $courseid == $data->sourcecourseid) {
            return;
        }

        // Collect series id for notifications.
        $this->series[] = $data->seriesid;

        // Assign Seriesid to new course and change ACL.
        $this->aclchanged[] = $apibridge->import_series_to_course_with_acl_change($courseid, $data->seriesid, $USER->id);
    }

    /**
     * Process the backuped data for import.
     *
     * @param array $data The import data needed for ACL change mode.
     * @return void
     */
    public function process_import($data) {

        $data = (object)$data;

        // Collect sourcecourseid for notifications.
        $this->sourcecourseid = $data->sourcecourseid;
    }


    /**
     * Send notifications after restore, to inform admins about errors.
     *
     * @return void
     */
    public function after_restore() {

        $courseid = $this->get_courseid();

        // Import mode is not defined.
        if (!$this->importmode) {
            notifications::notify_failed_importmode($courseid);
            return;
        }

        if ($this->importmode == 'duplication') {
            // None of the backupeventids are used for starting a workflow.
            if (!$this->series) {
                notifications::notify_failed_course_series($courseid, $this->backupeventids);
                return;
            }

            // A course series is created, but some events are not found on opencast server.
            if ($this->missingeventids) {
                notifications::notify_missing_events($courseid, $this->missingeventids);
            }
        } else if ($this->importmode == 'acl') {
            // The required data or the conditions to perform ACL change were missing.
            if (!$this->sourcecourseid) {
                notifications::notify_missing_sourcecourseid($courseid);
                return;
            }

            if (!$this->series) {
                notifications::notify_missing_seriesid($courseid);
                return;
            }
            // The ACL change import process is not successful.
            foreach ($this->aclchanged as $aclchange) {
                if ($aclchange->error == 1) {
                    if (!$aclchange->seriesaclchange) {
                        notifications::notify_failed_series_acl_change($courseid, $this->sourcecourseid, $aclchange->seriesid);
                        return;
                    }

                    if (!$aclchange->eventsaclchange && count($aclchange->eventsaclchange->failed) > 0) {
                        notifications::notify_failed_events_acl_change($courseid, $this->sourcecourseid,
                            $aclchange->eventsaclchange->failed);
                        return;
                    }

                    if (!$aclchange->seriesmapped) {
                        notifications::notify_failed_series_mapping($courseid, $this->sourcecourseid, $aclchange->seriesid);
                    }
                }
            }
        }
    }
}
