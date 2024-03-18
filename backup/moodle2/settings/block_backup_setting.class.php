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
 * Extends the backup settings class to control layout of checkbox.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the backup settings class to control layout of checkbox.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @author     Farbod Zamani Boroujeni (2024)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_block_opencast_setting extends backup_setting {


    /**
     * Create an instance of this class. Note that this is used to control level and layout of this setting.
     *
     * @param string $name Name of the setting
     * @param string $vtype Type of the setting
     * @param mixed $value Value of the setting
     * @param int $level Level of the setting
     * @param bool $visibility Is the setting visible in the UI
     * @param int $status Status of the setting with regards to the locking
     * @param array $attributes The arrtibutes of uisetting element
     */
    public function __construct($name, $vtype, $value = null, $level = self::COURSE_LEVEL, $visibility = self::VISIBLE,
        $status = self::NOT_LOCKED, $attributes = null) {

        // Set level.
        $this->level = $level;

        // In case attributes is empty, we set the default.
        if (empty($attributes)) {
            $attributes = [
                'class' => 'block-opencast-include',
            ];
        }

        // Parent construction.
        parent::__construct($name, $vtype, $value, $visibility, $status);

        // Making setting ui component (checkbox).
        $uisetting = new backup_setting_ui_checkbox(
            $this,
            $name,
            null,
            $attributes
        );
        // Set the icon to make the setting option more recognizable.
        $uisetting->set_icon(
            new image_icon(
                'monologo',
                get_string('pluginname', 'block_opencast'),
                'block_opencast',
                array('class' => 'iconlarge icon-post ml-1')
            )
        );

        // Set the setting ui component.
        $this->uisetting = $uisetting;
    }
}
