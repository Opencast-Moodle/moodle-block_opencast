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
 * External functions definitions.
 *
 * @package   block_opencast
 * @copyright 2021 Tamara Gunkel <tamara.gunkel@wi.uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'block_opencast_submit_series_form' => array(
        'classname' => 'block_opencast_external',
        'methodname' => 'submit_series_form',
        'classpath' => 'block/opencast/classes/external.php',
        'description' => 'Creates/Modifies a series',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/opencast:createseriesforcourse'
    ),
    'block_opencast_get_series_titles' => array(
        'classname' => 'block_opencast_external',
        'methodname' => 'get_series_titles',
        'classpath' => 'block/opencast/classes/external.php',
        'description' => 'Retrieves series titles',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/opencast:defineseriesforcourse'
    ),
    'block_opencast_import_series' => array(
        'classname' => 'block_opencast_external',
        'methodname' => 'import_series',
        'classpath' => 'block/opencast/classes/external.php',
        'description' => 'Imports a series into a course',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/opencast:importseriesintocourse'
    ),
    'block_opencast_unlink_series' => array(
        'classname' => 'block_opencast_external',
        'methodname' => 'unlink_series',
        'classpath' => 'block/opencast/classes/external.php',
        'description' => 'Removes a series from a course',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/opencast:manageseriesforcourse'
    ),
    'block_opencast_set_default_series' => array(
        'classname' => 'block_opencast_external',
        'methodname' => 'set_default_series',
        'classpath' => 'block/opencast/classes/external.php',
        'description' => 'Sets a new default series for a course',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/opencast:manageseriesforcourse'
    ),
    'block_opencast_get_default_series' => array(
        'classname' => 'block_opencast_external',
        'methodname' => 'get_default_series',
        'classpath' => 'block/opencast/classes/external.php',
        'description' => 'Get default opencast series for course and create if it does not exist',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/opencast:manageseriesforcourse'
    )
);
