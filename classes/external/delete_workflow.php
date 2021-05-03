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


namespace block_opencast\external;

use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External function 'delete_workflow' implementation.
 *
 * @package     block_opencast
 * @category    external
 * @copyright   2021 Tamara Gunkel, University of Münster
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_workflow extends external_api
{


    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'id of workflow'),
            )
        );
    }


    public static function execute($id)
    {
        global $DB;
        require_capability('moodle/site:config', context_system::instance());

        $params = self::validate_parameters(self::execute_parameters(), array('id' => $id));

        try {
            $DB->delete_records('block_opencast_workflowdefs', ['id' => $params['id']]);
        } catch (dml_exception $e) {
            return ['success' => false];
        }
        return ['success' => true];
    }


    public static function execute_returns()
    {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'True if successful')
            )
        );
    }
}
