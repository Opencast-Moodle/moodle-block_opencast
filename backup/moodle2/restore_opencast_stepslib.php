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
 * @author     Andreas Wagner
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

use block_opencast\local\apibridge;
use block_opencast\local\event;
use block_opencast\local\notifications;
use block_opencast\local\importvideosmanager;

/**
 * Define all the restore steps that will be used by the restore opencast block task.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_opencast_block_structure_step extends restore_structure_step {


    /** @var array Ids of the videos included in the backup. */
    private $backupeventids = [];
    /** @var array Ids of the videos that could not be found. */
    private $missingeventids = [];
    /** @var array Series in the backup. */
    private $series = [];
    /** @var array True if the ACls were changed. */
    private $aclchanged = [];
    /** @var int Opencast instance id */
    private $ocinstanceid;
    /** @var string Duplication or ACL import mode */
    private $importmode;
    /** @var string Restore unique id */
    private $restoreuniqueid;
    /** @var array Series ids that were not able to be saved as for import mapping */
    private $missingimportmappingseriesids = [];
    /** @var array Event ids that were not able to be saved as for import mapping */
    private $missingimportmappingeventids = [];

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

        // Generate restore unique identifier,
        // to keep track of restore session in later stages e.g. module mapping and repair.
        $this->restoreuniqueid = uniqid('oc_restore_' . $ocinstanceid . '_' . $courseid);

        $paths = [];

        // Get apibridge instance.
        $apibridge = apibridge::get_instance($ocinstanceid);

        // Get the import mode to decide the way of importing opencast videos.
        $importmode = get_config('tool_opencast', 'importmode_' . $ocinstanceid);
        $this->importmode = $importmode;

        // If ACL Change is the mode.
        if ($importmode == 'acl') {
            // Processing series, grouped by import.
            $paths[] = new restore_path_element('import', '/block/opencast/import', true);
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

            // Processing events, grouped by main opencast, in order to get series as well.
            $paths[] = new restore_path_element('opencast', '/block/opencast', true);
            $paths[] = new restore_path_element('events', '/block/opencast/events');
            $paths[] = new restore_path_element('event', '/block/opencast/events/event');

            // Adding import property here, to access series.
            $paths[] = new restore_path_element('import', '/block/opencast/import');
            $paths[] = new restore_path_element('series', '/block/opencast/import/series');
        }

        return $paths;
    }

    /**
     * Process the backuped data mainly duplicate events mode.
     *
     * @param array $data
     * @return void
     * @throws base_step_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function process_opencast($data) {
        $data = (object) $data;

        // Check if all required information is available.
        if (empty($this->series) || !isset($data->import) || !isset($data->events) ||
            empty($data->import[0]['series']) || empty($data->events['event'])) {
            // Nothing to do here, as the data is not enough.
            return;
        }

        // Get API Bridge to check the event or series validity.
        $apibridge = apibridge::get_instance($this->ocinstanceid);

        $courseid = $this->get_courseid();

        // Proceed with the backedup series, to save the mapping and repair the modules.
        foreach ($data->import[0]['series'] as $series) {
            $seriesid = $series['seriesid'] ?? null;
            // Skip when there is no original series, or the series is invalid.
            if (empty($seriesid) || !$apibridge->ensure_series_is_valid($seriesid)) {
                continue;
            }
            // Record series mapping for module fix.
            $issaved = importvideosmanager::save_series_import_mapping_record(
                $this->ocinstanceid,
                $courseid,
                $seriesid,
                $this->restoreuniqueid
            );
            if (!$issaved) {
                $this->missingimportmappingseriesids[] = $seriesid;
            }
        }

        foreach ($data->events['event'] as $event) {
            $eventid = $event['eventid'] ?? null;
            $this->backupeventids[] = $eventid;

            // Only duplicate, when the event exists in opencast.
            if (!$apibridge->get_already_existing_event([$eventid])) {
                $this->missingeventids[] = $eventid;
            } else {
                // Check for and record the module mappings.
                $issaved = importvideosmanager::save_episode_import_mapping_record(
                    $this->ocinstanceid,
                    $courseid,
                    $eventid,
                    $this->restoreuniqueid
                );
                if (!$issaved) {
                    $this->missingimportmappingeventids[] = $eventid;
                }

                // Add the duplication task.
                event::create_duplication_task(
                    $this->ocinstanceid,
                    $courseid,
                    $this->series[0],
                    $eventid,
                    false,
                    null,
                    $this->restoreuniqueid
                );
            }
        }
    }

    /**
     * Restore series under import path which contains series, mainly for ACL Change import mode.
     * @param object $data
     * @return void
     * @throws base_step_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function process_import($data) {
        global $USER;

        $data = (object) $data;

        // Collect sourcecourseid for further processing.
        $this->sourcecourseid = $data->sourcecourseid;

        // Check, target series.
        $courseid = $this->get_courseid();

        // First level checker.
        // Exit when the course by any chance wanted to restore itself.
        if (!empty($this->sourcecourseid) && $courseid == $this->sourcecourseid) {
            return;
        }

        // Get apibridge instance, to ensure series validity and edit series mapping.
        $apibridge = apibridge::get_instance($this->ocinstanceid);

        if (isset($data->series)) {
            foreach ($data->series as $series) {
                $seriesid = $series['seriesid'];

                // Second level checker.
                // Exit when there is no original series, or the series is invalid.
                if (empty($seriesid) || !$apibridge->ensure_series_is_valid($seriesid)) {
                    continue;
                }

                // Collect series id for notifications.
                $this->series[] = $seriesid;

                // Assign Seriesid to new course and change ACL.
                $this->aclchanged[] = $apibridge->import_series_to_course_with_acl_change($courseid, $seriesid, $USER->id);
            }
        }
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

            // Notify those series that were unable to have an import mapping record.
            if (!empty($this->missingimportmappingseriesids)) {
                notifications::notify_missing_import_mapping($courseid, $this->missingimportmappingseriesids, 'series');
            }

            // Notify those events that were unable to have an import mapping record.
            if (!empty($this->missingimportmappingeventids)) {
                notifications::notify_missing_import_mapping($courseid, $this->missingimportmappingeventids, 'events');
            }

            // Set the completion for mapping.
            $report = importvideosmanager::set_import_mapping_completion_status($this->restoreuniqueid);
            // If it is report has values, that means there were failures and we report them.
            if (is_array($report)) {
                notifications::notify_incompleted_import_mapping_records($courseid, $report);
            }

            // After all, we proceed to fix the series modules because they should not wait for the duplicate workflow to finish!
            importvideosmanager::fix_imported_series_modules_in_new_course(
                $this->ocinstanceid,
                $courseid,
                $this->series[0],
                $this->restoreuniqueid
            );
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
