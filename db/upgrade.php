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

    if ($oldversion < 2018080300) {

        // Define table block_opencast_draftitemid to be created.
        $table = new xmldb_table('block_opencast_draftitemid');

        // Adding fields to table block_opencast_draftitemid.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_draftitemid.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_opencast_draftitemid.
        $table->add_index('ctx-itemid', XMLDB_INDEX_NOTUNIQUE, array('contextid', 'itemid'));

        // Conditionally launch create table for block_opencast_draftitemid.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2018080300, 'opencast');
    }

    if ($oldversion < 2018082800) {

        // Define table block_opencast_series to be created.
        $table = new xmldb_table('block_opencast_series');

        // Conditionally launch create table for block_opencast_series.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Define field permanent to be added to block_opencast_roles.
        $table = new xmldb_table('block_opencast_roles');
        $field = new xmldb_field('permanent', XMLDB_TYPE_INTEGER, 1, true, XMLDB_NOTNULL, null, 1, 'actions');

        // Conditionally launch add field permanent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table block_opencast_deletejob to be created.
        $table = new xmldb_table('block_opencast_deletejob');

        // Adding fields to table block_opencast_deletejob.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('opencasteventid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('failed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_deletejob.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_opencast_deletejob.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2018082800, 'opencast');
    }

    if ($oldversion < 2018082802) {

        // Define table block_opencast_groupaccess to be created.
        $table = new xmldb_table('block_opencast_groupaccess');

        // Adding fields to table block_opencast_groupaccess.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('opencasteventid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groups', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_groupaccess.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_opencast_groupaccess.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2018082802, 'opencast');
    }

    if ($oldversion < 2019082100) {

        $table = new xmldb_table('block_opencast_uploadjob');

        // Changing nullability of field contenthash on table block_opencast_uploadjob to null.
        $field = new xmldb_field('contenthash', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'fileid');
        // Launch change of nullability for field contenthash.
        $dbman->change_field_notnull($table, $field);
        // Launch rename field contenthash.
        $dbman->rename_field($table, $field, 'contenthash_presentation');

        // Changing nullability of field fileid on table block_opencast_uploadjob to null.
        $field = new xmldb_field('fileid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        // Launch change of nullability for field fileid.
        $dbman->change_field_notnull($table, $field);
        // Launch rename field fileid.
        $dbman->rename_field($table, $field, 'presentation_fileid');

        // Define field presenter_fileid to be added to block_opencast_uploadjob.
        $field = new xmldb_field('presenter_fileid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');

        // Conditionally launch add field presenter_fileid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field contenthash_presenter to be added to block_opencast_uploadjob.
        $field = new xmldb_field('contenthash_presenter', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'presenter_fileid');

        // Conditionally launch add field contenthash_presenter.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Define table block_opencast_catalog to be created.
        $table = new xmldb_table('block_opencast_catalog');
        
        // Adding fields to table block_opencast_catalog.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('datatype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('required', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('readonly', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('param_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        
        // Adding keys to table block_opencast_catalog.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        
        // Conditionally launch create table for block_opencast_catalog.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


        // Define table block_opencast_metadata to be created.
        $table = new xmldb_table('block_opencast_metadata');

        // Adding fields to table block_opencast_metadata.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('uploadjobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table block_opencast_metadata.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uploadjob_foreign', XMLDB_KEY_FOREIGN, ['uploadjobid'], 'block_opencast_uploadjob', ['id']);

        // Conditionally launch create table for block_opencast_metadata.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        //Catalog
        $catalog = array();
        $catalog[0] = new \stdClass();
        $catalog[0]->name = 'title';
        $catalog[0]->datatype = 'text';
        $catalog[0]->required = 1;
        $catalog[0]->readonly = 0;
        $catalog[0]->param_json = '{"style":"min-width: 27ch;"}';

        $catalog[1] = new \stdClass();
        $catalog[1]->name = 'subjects';
        $catalog[1]->datatype = 'autocomplete';
        $catalog[1]->required = 0;
        $catalog[1]->readonly = 0;
        $catalog[1]->param_json = null;

        $catalog[2] = new \stdClass();
        $catalog[2]->name = 'description';
        $catalog[2]->datatype = 'textarea';
        $catalog[2]->required = 0;
        $catalog[2]->readonly = 0;
        $catalog[2]->param_json = '{"rows":"3","cols":"19"}';

        $catalog[3] = new \stdClass();
        $catalog[3]->name = 'language';
        $catalog[3]->datatype = 'select';
        $catalog[3]->required = 0;
        $catalog[3]->readonly = 0;
        $catalog[3]->param_json = '{"":"No option selected","slv":"Slovenian","por":"Portugese","roh":"Romansh","ara":"Arabic","pol":"Polish","ita":"Italian","zho":"Chinese","fin":"Finnish","dan":"Danish","ukr":"Ukrainian","fra":"French","spa":"Spanish","gsw":"Swiss German","nor":"Norwegian","rus":"Russian","jpx":"Japanese","nld":"Dutch","tur":"Turkish","hin":"Hindi","swa":"Swedish","eng":"English","deu":"German"}';

        $catalog[4] = new \stdClass();
        $catalog[4]->name = 'rightsHolder';
        $catalog[4]->datatype = 'text';
        $catalog[4]->required = 0;
        $catalog[4]->readonly = 0;
        $catalog[4]->param_json = '{"style":"min-width: 27ch;"}';

        $catalog[5] = new \stdClass();
        $catalog[5]->name = 'license';
        $catalog[5]->datatype = 'select';
        $catalog[5]->required = 0;
        $catalog[5]->readonly = 0;
        $catalog[5]->param_json = '{"":"No option selected","ALLRIGHTS":"All Rights Reserved","CC0":"CC0","CC-BY-ND":"CC BY-ND","CC-BY-NC-ND":"CC BY-NC-ND","CC-BY-NC-SA":"CC BY-NC-SA","CC-BY-SA":"CC BY-SA","CC-BY-NC":"CC BY-NC","CC-BY":"CC BY"}';

        $catalog[6] = new \stdClass();
        $catalog[6]->name = 'creator';
        $catalog[6]->datatype = 'autocomplete';
        $catalog[6]->required = 0;
        $catalog[6]->readonly = 0;
        $catalog[6]->param_json = null;

        $catalog[7] = new \stdClass();
        $catalog[7]->name = 'contributor';
        $catalog[7]->datatype = 'autocomplete';
        $catalog[7]->required = 0;
        $catalog[7]->readonly = 0;
        $catalog[7]->param_json = null;

        // Insert new catalog.
        $DB->insert_records('block_opencast_catalog', $catalog);

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2019082100, 'opencast');
    }


    return true;
}
