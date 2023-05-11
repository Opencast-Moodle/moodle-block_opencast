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
 * Events.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use local_chunkupload\local\chunkupload_file;

/**
 * Events.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event {

    /** @var array Access control list. */
    private $acl = array();
    /** @var array Meta data */
    private $metadatafields = array();
    /** @var object Video file */
    private $presentation = null;
    /** @var object Video file */
    private $presenter = null;

    /**
     * Add a id-value pair as metadata for flavour dublincore/episode
     *
     * @param int $id
     * @param string $value
     */
    public function add_meta_data($id, $value) {
        $this->metadatafields[] = array('id' => $id, 'value' => $value);
    }

    /**
     * Get dublincore/episode metadata for the event.
     *
     * @return string json encoded metadata.
     */
    public function get_meta_data() {

        $metadata = array();
        $metadata['label'] = "Opencast Series Dublincore";
        $metadata['flavor'] = "dublincore/episode";
        $metadata['fields'] = $this->metadatafields;

        return json_encode(array($metadata));
    }

    /**
     * Set presentation as a stored file from moodle.
     *
     * @param int $fileid
     */
    public function set_presentation($fileid) {
        $fs = get_file_storage();
        $this->presentation = $fs->get_file_by_id($fileid);
    }

    /**
     * Set presenter as a chunkupload file from moodle.
     *
     * @param string $chunkuploadid
     * @throws \moodle_exception
     */
    public function set_chunkupload_presenter($chunkuploadid) {
        if (!class_exists('\local_chunkupload\chunkupload_form_element')) {
            throw new \moodle_exception("local_chunkupload is not installed. This should never happen.");
        }
        $this->presenter = new chunkupload_file($chunkuploadid);
    }

    /**
     * Set presentation as a chunkupload file from moodle.
     *
     * @param string $chunkuploadid
     * @throws \moodle_exception
     */
    public function set_chunkupload_presentation($chunkuploadid) {
        if (!class_exists('\local_chunkupload\chunkupload_form_element')) {
            throw new \moodle_exception("local_chunkupload is not installed. This should never happen.");
        }
        $this->presentation = new chunkupload_file($chunkuploadid);
    }

    /**
     * Get the presentation (i. e. the video file).
     *
     * @return \stored_file
     */
    public function get_presentation() {
        return $this->presentation;
    }

    /**
     * Set presenter as a stored file from moodle.
     *
     * @param int $fileid
     */
    public function set_presenter($fileid) {
        $fs = get_file_storage();
        $this->presenter = $fs->get_file_by_id($fileid);
    }

    /**
     * Get the presenter (i. e. the video file).
     *
     * @return \stored_file
     */
    public function get_presenter() {
        return $this->presenter;
    }
    // End adding presenter option.

    /**
     * Set the acl data for this event.
     *
     * @param string|array $jsonacl acl arry or string as received from opencast.
     * @throws \moodle_exception
     */
    public function set_json_acl($jsonacl) {

        $jsonacl = is_string($jsonacl) ? json_decode($jsonacl) : $jsonacl;
        $this->acl = $jsonacl;

        if (!is_array($this->acl)) {
            throw new \moodle_exception('invalidacldata', 'block_opencast');
        }
    }

    /**
     * Add a acl rule.
     *
     * @param boolean $allow
     * @param string $action
     * @param string $role
     */
    public function add_acl($allow, $action, $role) {

        $this->remove_acl($action, $role);
        $this->acl[] = (object)array('allow' => $allow, 'role' => $role, 'action' => $action);
    }

    /**
     * Returns true if a given acl role exists.
     * @param bool $allow If allowed
     * @param string $action Action
     * @param string $role Role name
     */
    public function has_acl($allow, $action, $role) {
        $role = (object)array('allow' => $allow, 'role' => $role, 'action' => $action);
        return in_array($role, $this->acl);
    }

    /**
     * Remove a acl rule.
     * @param string $action Action
     * @param string $role Role name
     */
    public function remove_acl($action, $role) {

        foreach ($this->acl as $key => $acl) {
            if (($acl->action == $action) && ($acl->role == $role)) {
                unset($this->acl[$key]);
            }
        }
    }

    /**
     * Get a course id based on acl.
     * Note: if more than one roles are assigned take the first we detect.
     *
     * @return string
     */
    public function get_next_series_courseid() {

        if (!$this->acl) {
            return false;
        }

        $filter = "/" . \tool_opencast\local\api::get_course_acl_role_prefix() . "([0-9]*)/";

        foreach ($this->acl as $acl) {
            $matches = array();
            if (preg_match($filter, $acl->role, $matches) && ($acl->allow == 1)) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Get the acl rules as array.
     * @return array
     */
    public function get_acl() {
        return $this->acl;
    }

    /**
     * Get the acl rules as a json object.
     *
     * @return string.
     */
    public function get_json_acl() {
        return json_encode(array_values($this->acl));
    }

    /**
     * Return the processing workflow as a json object.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return string
     */
    public function get_processing($ocinstanceid) {

        $uploadworkflow = get_config('block_opencast', 'uploadworkflow_' . $ocinstanceid);
        if (empty($uploadworkflow)) {
            $uploadworkflow = 'ng-schedule-and-upload';
        }

        $publistoengage = get_config('block_opencast', 'publishtoengage_' . $ocinstanceid);
        $publistoengage = (empty($publistoengage)) ? "false" : "true";

        $processing = array();
        $processing['workflow'] = $uploadworkflow;
        $processing['configuration'] = array(
            "flagForCutting" => "false",
            "flagForReview" => "false",
            "publishToEngage" => $publistoengage,
            "publishToHarvesting" => "false",
            "straightToPublishing" => "true"
        );
        return json_encode($processing);
    }

    /**
     * Get all form params to create a new event in opencast via api.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @return array form params.
     */
    public function get_form_params($ocinstanceid) {

        $params = array();
        $params['acl'] = $this->get_json_acl();
        $params['metadata'] = $this->get_meta_data();
        // Handling presentation & presenter.
        if ($this->get_presenter()) {
            $params['presenter'] = $this->get_presenter();
        }
        if ($this->get_presentation()) {
            $params['presentation'] = $this->get_presentation();
        }
        $params['processing'] = $this->get_processing($ocinstanceid);

        return $params;
    }

    /**
     * Create an adhoc task that will start duplication workflows.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param string $courseid The course ID.
     * @param string $seriesid The course series identifier.
     * @param string $eventid The event identifier.
     * @param bool $modulecleanup (optional) The switch if we want to cleanup the episode modules.
     * @param array|null $episodemodules (optional) The array of episode modules to be cleaned up.
     * @return mixed false if task could not be created, id of inserted task otherwise.
     */
    public static function create_duplication_task($ocinstanceid, $courseid, $seriesid,
                                                   $eventid, $modulecleanup = false, $episodemodules = null) {

        $task = new \block_opencast\task\process_duplicate_event();

        $data = (object)[
            'ocinstanceid' => $ocinstanceid,
            'courseid' => $courseid,
            'seriesid' => $seriesid,
            'eventid' => $eventid,
            'schedulemodulecleanup' => $modulecleanup,
            'episodemodules' => $episodemodules
        ];
        $task->set_custom_data($data);
        return \core\task\manager::queue_adhoc_task($task, true);
    }
}
