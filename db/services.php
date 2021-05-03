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

defined('MOODLE_INTERNAL') || die();

$services = array(
    'deleteworkflow' => array(
        'functions' => array('block_opencast_delete_workflow'),
        'requiredcapability' => 'moodle/site:config',
        'restrictedusers' => 0,
        'enabled' => 1,
        'downloadfiles' => 0,
        'uploadfiles' => 0
    )
);

$functions = array(
    'block_opencast_delete_workflow' => array(
        'classname' => 'block_opencast_external',
        'methodname' => 'delete_workflow',
        'classpath' => 'blocks/opencast/externallib.php',
        'description' => 'Deletes a workflow.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/site:config',
    ),
);