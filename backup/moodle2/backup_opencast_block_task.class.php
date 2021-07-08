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
 * Define settings of the backup tasks for the opencast block.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/blocks/opencast/backup/moodle2/backup_opencast_stepslib.php');
require_once($CFG->dirroot . '/blocks/opencast/backup/moodle2/settings/block_backup_setting.class.php');

/**
 * Define settings of the backup taks for the opencast block.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_opencast_block_task extends backup_block_task {

    /**
     * Add a setting to backup process, when course videos are available.
     */
    protected function define_my_settings() {
        $ocinstances = json_decode(get_config('tool_opencast', 'ocinstances'));
        foreach($ocinstances as $ocinstance) {
            // Check whether this feature is enabled and working at all.
            if (\block_opencast\local\importvideosmanager::is_enabled_and_working_for_coreimport($ocinstance->id) == true) {

                // Check, whether there are course videos available.
                $apibridge = \block_opencast\local\apibridge::get_instance($ocinstance->id);
                $courseid = $this->get_courseid();
                $videostobackup = $apibridge->get_course_videos_for_backup($courseid);

                if (count($videostobackup) > 0) {
                    // TODO if only default instance print different string

                    $setting = new backup_block_opencast_setting('opencast_videos_include_' . $ocinstance->id, base_setting::IS_BOOLEAN, false);
                    $setting->get_ui()->set_label(get_string('backupopencastvideos', 'block_opencast', $ocinstance->name));
                    $this->add_setting($setting);
                    $this->plan->get_setting('blocks')->add_dependency($setting);
                }
            }
        }
    }

    /**
     * Add the structure step, when course videos are available.
     */
    protected function define_my_steps() {
        $ocinstances = json_decode(get_config('tool_opencast', 'ocinstances'));
        foreach($ocinstances as $ocinstance) {
            if (!$this->setting_exists('opencast_videos_include_' . $ocinstance->id)) {
                continue;
            }

            if ($this->get_setting_value('opencast_videos_include_' . $ocinstance->id)) {
                $this->add_step(new backup_opencast_block_structure_step('opencast_structure_' . $ocinstance->id, 'opencast_'.$ocinstance->id.'.xml'));
            }
        }
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
        return [];
    }

    /**
     * We have no special encoding of links.
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        return $content;
    }

}
