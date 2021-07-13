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
 * Helper class for workflow settings.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use tool_opencast\empty_configuration_exception;

defined('MOODLE_INTERNAL') || die();

/**
 *  Helper class for workflow settings.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workflow_setting_helper
{
    public static function validate_workflow_setting($data) {
        // Hack to get the opencast instance id.
        $category = optional_param('category', null, PARAM_RAW);
        if($category) {
            $ocinstanceid = intval(ltrim($category, 'block_opencast_instance_'));
        }
        else {
            $section = optional_param('section', null, PARAM_RAW);
            $ocinstanceid = intval(ltrim($section, 'block_opencast_importvideossettings_'));
        }

        // Do only if a workflow was set.
        if ($data != null) {
            // Get an APIbridge instance.
            $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

            // Verify if the given value is a valid Opencast workflow.
            if (!$apibridge->check_if_workflow_exists($data)) {
                return get_string('workflow_not_existing', 'block_opencast');
            }
            return false;
        }

        return false;
    }

    public static function load_workflow_choices($ocinstanceid, $workflowtag) {
        // Don't load anything during initial installation.
        // This is important as the Opencast API is not set up during initial installation.
        if (during_initial_install()) {
            return null;
        }

        // Get the available workflows.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Set workflows as choices. This is even done if there aren't any (real) workflows returned.
        try {
            return $apibridge->get_available_workflows_for_menu($workflowtag, true);

            // Something went wrong and the list of workflows could not be retrieved.
        } catch (opencast_connection_exception | empty_configuration_exception $e) {
            return $e;
        }
    }
}
