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
 * Admin setting class which is used to create a hidden help button.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use admin_setting;

/**
 * Admin setting class which is used to create a hidden help button.
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_hiddenhelpbtn extends admin_setting {

    /** @var string Id of the div tag */
    private $divid;

    /** @var string Name for help icon creation */
    private $iconname;

    /** @var string Component */
    private $component;

    /**
     * Not a setting, just an editable table.
     * @param string $name Setting name
     * @param string $divid Id of the div tag
     * @param string $iconname Icon that is displayed as help button
     * @param string $component Component
     */
    public function __construct($name, $divid, $iconname, $component) {
        $this->nosave = true;
        $this->divid = $divid;
        $this->iconname = $iconname;
        $this->component = $component;
        parent::__construct($name, '', '', '');
    }

    /**
     * Always returns true
     *
     * @return bool Always returns true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns true
     *
     * @return bool Always returns true
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Never write settings
     *
     * @param mixed $data Gets converted to str for comparison against yes value
     * @return string Always returns an empty string
     */
    public function write_setting($data) {
        // Do not write any setting.
        return '';
    }

    /**
     * Returns an HTML string
     *
     * @param string $data
     * @param string $query
     * @return string Returns an HTML string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;
        return '<div class="d-none" id="' . $this->divid . '">' .
            $OUTPUT->help_icon($this->iconname, $this->component) .
            '</div>';
    }
}
