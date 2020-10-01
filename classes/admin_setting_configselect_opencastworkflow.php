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
 * Admin setting class which is used to set an Opencast workflow.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin setting class which is used to set an Opencast workflow.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configselect_opencastworkflow extends \admin_setting_configselect {

    /**
     * Save a setting.
     *
     * @param string $data
     * @return string empty of error string
     */
    public function write_setting($data) {
        // Validate data before storage.
        // The parent class admin_setting_configselect does not do that itself, unfortunately.
        // Thus, we have to override write_setting() as well here.
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }

        return parent::write_setting($data);
    }

    /**
     * Validate data before storage.
     *
     * @param string data
     * @return mixed Returns true if ok, a string if an error was found
     */
    public function validate($data) {
        // Do only if a workflow was set
        if ($data != null) {
            // Get an APIbridge instance.
            $apibridge = \block_opencast\local\apibridge::get_instance();

            // Verify if the given value is a valid Opencast workflow.
            if (!$apibridge->check_if_workflow_exists($data)) {
                return get_string('workflow_not_existing', 'block_opencast');
            }
        }

        // Normally, we would call parent::validate($data) here
        // But as admin_setting_configselect does not validate, we are done here.
        return true;
    }
}
