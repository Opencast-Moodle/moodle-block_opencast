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
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute opencast upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_opencast_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2017110708) {

        // Define table block_opencast_roles to be created.
        $table = new xmldb_table('block_opencast_roles');

        // Adding fields to table block_opencast_roles.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('rolename', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null);
        $table->add_field('actionname', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_roles.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_opencast_roles.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2017110708, 'opencast');
    }
    if ($oldversion < 2018012500) {

        // Rename field actions on table block_opencast_roles to actions.
        $table = new xmldb_table('block_opencast_roles');
        $field = new xmldb_field('actionname', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'rolename');

        // Launch change of type for field actions.
        $dbman->change_field_type($table, $field);
        // Launch rename field actions.
        $dbman->rename_field($table, $field, 'actions');

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2018012500, 'opencast');
    }

    if ($oldversion < 2018012501) {

        // Define table block_opencast_series to be created.
        $table = new xmldb_table('block_opencast_series');

        // Adding fields to table block_opencast_series.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('series', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_series.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('fk_course', XMLDB_KEY_FOREIGN_UNIQUE, array('courseid'), 'course', array('id'));

        // Conditionally launch create table for block_opencast_series.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2018012501, 'opencast');
    }

    if ($oldversion < 2018013003) {

        // Define table block_opencast_series to be created.
        $table = new xmldb_table('block_opencast_series');

        // Conditionally launch create table for block_opencast_series.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2018013003, 'opencast');
    }

    if ($oldversion < 2018041800) {

        // Define field permanent to be added to block_opencast_roles.
        $table = new xmldb_table('block_opencast_roles');
        $field = new xmldb_field('permanent', XMLDB_TYPE_INTEGER, 1, true, XMLDB_NOTNULL, null, 1, 'actions');

        // Conditionally launch add field permanent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2018041800, 'opencast');
    }

    return true;
}
