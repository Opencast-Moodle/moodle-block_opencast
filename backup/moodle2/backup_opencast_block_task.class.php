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
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\apibridge;
use block_opencast\local\importvideosmanager;
use tool_opencast\local\settings_api;

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
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_opencast_block_task extends backup_block_task {


    /**
     * Add a setting to backup process, when course videos are available.
     */
    protected function define_my_settings() {
        $ocinstances = settings_api::get_ocinstances();
        foreach ($ocinstances as $ocinstance) {
            // Check whether this feature is enabled and working at all.
            if (importvideosmanager::is_enabled_and_working_for_coreimport($ocinstance->id) == true) {

                // Get default value, to include opencast video.
                $defaultimportvalue = boolvar(get_config('block_opencast', 'importvideoscoredefaultvalue_' . $ocinstance->id));

                // Get import mode, to determine whether to offer selective feature or not.
                // Duplicate videos mode is capable of selection.
                // ACL Change mode is not, due to changing the acl of the whole series at once.
                $importmode = get_config('block_opencast', 'importmode_' . $ocinstance->id);

                // Check, whether there are course videos available.
                $apibridge = apibridge::get_instance($ocinstance->id);
                $courseid = $this->get_courseid();

                $seriestobackup = $apibridge->get_course_series($courseid);

                // A flag to check if the main include is added.
                $ocinstanceisincluded = false;
                $includesettingname = 'opencast_videos_' . $ocinstance->id . '_included';
                // Course level setting inclusion.
                $setting = new backup_block_opencast_setting(
                    $includesettingname,
                    base_setting::IS_BOOLEAN,
                    $defaultimportvalue
                );
                $setting->get_ui()->set_label(get_string('backupopencastvideos', 'block_opencast', $ocinstance->name));

                foreach ($seriestobackup as $series) {
                    $seriesobj = $apibridge->get_series_by_identifier($series->series, false);

                    if (empty($seriesobj)) {
                        continue;
                    }

                    $result = $apibridge->get_series_videos($seriesobj->identifier);

                    $videostobackup = [];
                    foreach ($result->videos as $video) {
                        if ($video->processing_state == 'SUCCEEDED') {
                            $videostobackup[$video->identifier] = $video;
                        }
                    }

                    if (count($videostobackup) > 0) {
                        // Here we make sure that the main inclusion happens only once.
                        if (!$ocinstanceisincluded) {
                            $this->add_setting($setting);
                            $this->plan->get_setting('blocks')->add_dependency($setting);
                            $ocinstanceisincluded = true;
                        }
                        // Section Level setting for series.
                        $seriessettingname =
                            'opencast_videos_' . $ocinstance->id . '_series_' . $seriesobj->identifier . '_included';
                        $seriessetting = new backup_block_opencast_setting(
                            $seriessettingname,
                            base_setting::IS_BOOLEAN,
                            $defaultimportvalue,
                            backup_block_opencast_setting::SECTION_LEVEL
                        );
                        $stringobj = new \stdClass();
                        $stringobj->title = $seriesobj->title;
                        // To avoid cluttered ui and ugly display, we present only the last 6 digit of the id.
                        $stringobj->identifier = '***' . substr($seriesobj->identifier, -6);
                        $seriessetting->get_ui()->set_label(
                            get_string('importvideos_wizard_series_cb_title', 'block_opencast', $stringobj)
                        );
                        // Adding the help button to emphasize that in ACL Change only series selection is possible.
                        if ($importmode === 'acl') {
                            $seriessetting->set_help(
                                'importvideos_wizard_unselectableeventreason',
                                'block_opencast'
                            );
                        }
                        $this->add_setting($seriessetting);
                        $this->get_setting($includesettingname)->add_dependency($seriessetting, setting_dependency::DISABLED_NOT_CHECKED);

                        foreach ($videostobackup as $bkvideo) {
                            // Activity level settings for episodes.
                            $status = backup_block_opencast_setting::NOT_LOCKED;
                            // Locking the video selection in ACL Change.
                            if ($importmode === 'acl') {
                                $status = backup_block_opencast_setting::LOCKED_BY_CONFIG;
                            }

                            $episodesetting = new backup_block_opencast_setting(
                                'opencast_videos_' . $ocinstance->id . '_episode_' . $bkvideo->identifier . '_included',
                                base_setting::IS_BOOLEAN,
                                $defaultimportvalue,
                                backup_block_opencast_setting::ACTIVITY_LEVEL,
                                backup_block_opencast_setting::VISIBLE,
                                $status
                            );
                            $stringobj = new \stdClass();
                            $stringobj->title = $bkvideo->title;
                            // To avoid cluttered ui and ugly display, we present only the last 6 digit of the id.
                            $stringobj->identifier = '***' . substr($bkvideo->identifier, -6);
                            $episodesetting->get_ui()->set_label(
                                get_string('importvideos_wizard_event_cb_title', 'block_opencast', $stringobj)
                            );
                            $this->add_setting($episodesetting);
                            $this->get_setting($seriessettingname)->add_dependency($episodesetting, setting_dependency::DISABLED_NOT_CHECKED);
                        }
                    }
                }
            }
        }
    }

    /**
     * Add the structure step, when course videos are available.
     */
    protected function define_my_steps() {
        $ocinstances = settings_api::get_ocinstances();
        $courseid = $this->get_courseid();
        foreach ($ocinstances as $ocinstance) {
            $includesettingname = 'opencast_videos_' . $ocinstance->id . '_included';
            // Checking the main level inclusion.
            if (!$this->setting_exists($includesettingname)) {
                continue;
            }

             // If the main level is included.
            if ($this->get_setting_value($includesettingname)) {
                // Get API Bridge to get data.
                $apibridge = apibridge::get_instance($ocinstance->id);
                $backupstructuredata = [];

                // Get course series to loop through and verify.
                $seriestobackup = $apibridge->get_course_series($courseid);
                foreach ($seriestobackup as $series) {
                    // Checking the series inclusion.
                    $seriessettingname =
                            'opencast_videos_' . $ocinstance->id . '_series_' . $series->series . '_included';
                    if (!$this->setting_exists($seriessettingname) || empty($this->get_setting_value($seriessettingname))) {
                        continue;
                    }

                    // Get the series video to lopp through and check the inclusion.
                    $result = $apibridge->get_series_videos($series->series);
                    foreach ($result->videos as $video) {
                        // Checking the episode inclusion.
                        $episodesettingname = 'opencast_videos_' . $ocinstance->id . '_episode_' . $video->identifier . '_included';
                        if (!$this->setting_exists($episodesettingname) || empty($this->get_setting_value($episodesettingname))) {
                            continue;
                        }
                        // We store the episode of series in backupstructuredata.
                        $backupstructuredata[$series->series][] = $video->identifier;
                    }
                }

                // Pass the collected backup data into the step.
                $backupstep = new backup_opencast_block_structure_step('opencast_structure_' . $ocinstance->id,
                    'opencast_' . $ocinstance->id . '.xml');
                $backupstep->set_data($backupstructuredata);
                $this->add_step($backupstep);
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
