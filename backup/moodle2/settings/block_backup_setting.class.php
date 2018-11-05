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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Extends the backup settings class to control layout of checkbox.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_block_opencast_setting extends backup_setting {

    /**
     * Create an instance of this class. Note that this is used to control level and layout of this setting.
     *
     * @param string $name Name of the setting
     * @param string $vtype Type of the setting, eg {@link self::IS_TEXT}
     * @param mixed $value Value of the setting
     * @param bool $visibility Is the setting visible in the UI, eg {@link self::VISIBLE}
     * @param int $status Status of the setting with regards to the locking, eg {@link self::NOT_LOCKED}
     */
    public function __construct($name, $vtype, $value = null, $visibility = self::VISIBLE, $status = self::NOT_LOCKED) {

        $this->level = self::COURSE_LEVEL;

        parent::__construct($name, $vtype, $value, $visibility, $status);
        $this->uisetting = new backup_setting_ui_checkbox($this, $name, null, ['class' => 'block-opencast-include']);
    }

}

// class restore_block_opencast_setting extends backup_block_opencast_setting {}