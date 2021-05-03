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
 * @package   blocks_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");

class block_opencast_external extends external_api
{

    public static function delete_workflow_parameters()
    {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'id of workflow'),
            )
        );
    }

    public static function delete_workflow_returns()
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'True if successful')
            )
        );
    }

    public static function delete_workflow($id)
    {
        global $DB;
        require_capability('moodle/site:config', context_system::instance());

        $params = self::validate_parameters(self::delete_workflow_parameters(), array('id' => $id));

        try {
            $DB->delete_records('block_opencast_workflowdefs', ['id' => $params['id']]);
        } catch (dml_exception $e) {
            return ['success' => false];
        }
        return ['success' => true];
    }
}
