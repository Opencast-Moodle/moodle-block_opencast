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
 * Helper class for admin settings.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use tool_opencast\empty_configuration_exception;

/**
 *  Helper class for admin settings.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_helper {

    /**
     * Validate if the selected workflow does indeed exist.
     *
     * @param string $data Setting value
     * @return false|\lang_string|string
     * @throws \coding_exception
     */
    public static function validate_workflow_setting($data) {
        if ($data == null) {
            return false;
        }

        // Hack to get the opencast instance id.
        $category = optional_param('category', null, PARAM_RAW);
        if ($category) {
            $ocinstanceid = intval(str_replace('block_opencast_instance_', '', $category));
        } else {
            $section = optional_param('section', null, PARAM_RAW);
            $ocinstanceid = intval(str_replace('block_opencast_importvideossettings_', '', $section));
        }

        // Get an APIbridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

        // Verify if the given value is a valid Opencast workflow.
        if (!$apibridge->check_if_workflow_exists($data)) {
            return get_string('workflow_not_existing', 'block_opencast');
        }
        return false;
    }

    /**
     * Returns available workflows with the given tag.
     * @param int $ocinstanceid Opencast instance id.
     * @param string $workflowtags comma separated list of tags
     * @return array|opencast_connection_exception|\Exception|empty_configuration_exception|null
     */
    public static function load_workflow_choices($ocinstanceid, $workflowtags) {
        // Don't load anything during initial installation.
        // This is important as the Opencast API is not set up during initial installation.
        if (during_initial_install()) {
            return null;
        }

        try {
            // Get the available workflows.
            $apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

            // Set workflows as choices. This is even done if there aren't any (real) workflows returned.
            return $apibridge->get_available_workflows_for_menu($workflowtags, true);

            // Something went wrong and the list of workflows could not be retrieved.
        } catch (opencast_connection_exception | empty_configuration_exception $e) {
            return $e;
        }
    }

    /**
     * Ensures that the owner setting corresponds to a role defined in the ACL table and
     * fulfills the requirements for the owner role.
     *
     * @param string $data Setting data
     * @return bool|\lang_string|string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function validate_aclownerrole_setting($data) {
        // Hack to get the opencast instance id.
        $category = optional_param('category', null, PARAM_RAW);
        if ($category) {
            $ocinstanceid = intval(ltrim($category, 'block_opencast_instance_'));
        } else {
            $section = optional_param('section', null, PARAM_RAW);
            $ocinstanceid = intval(ltrim($section ?? '', 'block_opencast_importvideossettings_'));
        }

        // Do only if a workflow was set.
        if (!empty($data)) {
            $roles = json_decode(get_config('block_opencast', 'roles_' . $ocinstanceid));
            $role = array_search($data, array_column($roles, 'rolename'));
            if ($role === false) {
                // Role isn't defined as ACL role.
                return get_string('role_not_defined', 'block_opencast');
            }

            if (!$roles[$role]->permanent) {
                // Role isn't defined as permanent.
                return get_string('role_not_permanent', 'block_opencast');
            }

            $userrelated = false;
            $userplaceholders = ['[USERNAME]', '[USERNAME_LOW]', '[USERNAME_UP]', '[USER_EMAIL]', '[USER_EXTERNAL_ID]'];
            foreach ($userplaceholders as $placeholder) {
                if (strpos($data, $placeholder) !== false) {
                    $userrelated = true;
                    break;
                }
            }

            if (!$userrelated) {
                // Role is not user-related.
                return get_string('role_not_user_related', 'block_opencast');
            }

            return true;
        }

        return true;
    }
}
