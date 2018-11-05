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
 * Define settings of the restore tasks for the opencast block.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/opencast/backup/moodle2/restore_opencast_stepslib.php');
require_once($CFG->dirroot . '/blocks/opencast/backup/moodle2/settings/block_restore_setting.class.php');

class restore_opencast_block_task extends restore_block_task {

    /**
     * Check, if it is possible to restore events into given target course.
     *
     * - the course has not series assinged OR
     * - we are importing into an existing course.
     *
     * @return boolean
     */
    private function can_restore_events() {

        $apibridge = \block_opencast\local\apibridge::get_instance();

        $courseid = $this->get_courseid();
        $seriesid = $apibridge->get_stored_seriesid($courseid);

        // If there is no course series assigned we may restore the events.
        if (is_null($seriesid)) {
            return true;
        }

        // If a course series exists but we are importing events may be imported.
        return ($this->plan->get_mode() == backup::MODE_IMPORT);
    }

    /**
     * Add a setting to restore process, when:
     *
     *   1. course videos are available in backupfile AND
     *   2. target course has not yet an assigned series AND
     *   3. api level v1.1.0 is supported.
     */
    protected function define_my_settings() {

        if (!file_exists($this->get_taskbasepath() . '/opencast.xml')) {
            return;
        }

        // Does API support workflows to start duplication of videos.
        $apibridge = \block_opencast\local\apibridge::get_instance();
        if (!$apibridge->supports_api_level('v1.1.0')) {
            return;
        }

        // Check, whether we may may import or restore events into the target course .
        $canrestore = $this->can_restore_events();
        $locktype = ($canrestore) ? backup_setting::NOT_LOCKED : backup_setting::LOCKED_BY_CONFIG;

        $setting = new restore_block_opencast_setting('opencast_videos_include', base_setting::IS_BOOLEAN, $canrestore, backup_setting::VISIBLE, $locktype);
        $setting->get_ui()->set_label(get_string('restoreopencastvideos', 'block_opencast'));

        $this->add_setting($setting);
    }

    /**
     * Add a restore step, when required.
     */
    protected function define_my_steps() {

        // Settings, does not exists, if opencast system does not support copiing workflow.
        if (!$this->setting_exists('opencast_videos_include')) {
            return;
        }

        if (!$this->get_setting_value('opencast_videos_include') && ($this->plan->get_mode() != backup::MODE_IMPORT)) {
            return;
        }

        // Try to create a course series, if neccessary.
        $apibridge = \block_opencast\local\apibridge::get_instance();
        $courseid = $this->get_courseid();

        if (!$seriesid = $apibridge->get_stored_seriesid($courseid)) {
            if (!$courseseries = $apibridge->create_course_series($courseid)) {
                echo get_string('seriesnotcreated', 'block_opencast');
            }
        }

        // Add the restore step to collect the events, that should have been restored.
        $this->add_step(new restore_opencast_block_structure_step('opencast_structure', 'opencast.xml'));
    }

    /**
     * No file areas are controlled by this block.
     * @return array
     */
    public function get_fileareas() {
        return [];
    }

    /**
     * We don't need to encode attrs in configdata.
     * @return array
     */
    public function get_configdata_encoded_attributes() {
        return array(); // We need to encode some attrs in configdata
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder.
     */
    public static function define_decode_rules() {
        return [];
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        return [];
    }

}
