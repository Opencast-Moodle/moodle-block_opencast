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
 * Opencast block installation.
 *
 * @package   block_opencast
 * @copyright 2017 Tamara Gunkel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Opencast block installation.
 * Creates default ACL roles.
 *
 * @package   block_opencast
 * @copyright 2017 Tamara Gunkel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_block_opencast_install() {
    global $DB;

    $record = array();
    $record[0] = new \stdClass();
    $record[0]->rolename = 'ROLE_ADMIN';
    $record[0]->actions = 'write,read';

    $record[1] = new \stdClass();
    $record[1]->rolename = 'ROLE_GROUP_MOODLE_COURSE_[COURSEID]';
    $record[1]->actions = 'read';

    // Insert new record.
    $DB->insert_records('block_opencast_roles', $record);

}

