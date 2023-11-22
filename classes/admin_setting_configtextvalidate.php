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
 * Admin setting class which provides a configtext with custom validation function.
 *
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use admin_setting_configtext;

/**
 * Admin setting class which provides a configtext with custom validation function.
 *
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configtextvalidate extends admin_setting_configtext
{
    /** @var callable|null Validation function */
    protected $validatefunction = null;

    /**
     * Sets a validate function.
     *
     * The callback will be passed one parameter, the new setting value, and should return either
     * true if the value is OK, or an error message if not.
     *
     * @param callable|null $validatefunction Validate function or null to clear
     */
    public function set_validate_function(?callable $validatefunction = null)
    {
        $this->validatefunction = $validatefunction;
    }

    /**
     * Validate data before storage
     * @param string $data New setting data
     * @return mixed true if ok string if error found
     */
    public function validate($data)
    {
        $valid = parent::validate($data);
        if ($valid === true) {
            // Parent validation was successful.
            // If validation function is specified, call it now.
            if ($this->validatefunction) {
                return call_user_func($this->validatefunction, $data);
            } else {
                return true;
            }
        } else {
            return $valid;
        }
    }
}
