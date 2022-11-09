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
 * Execute opencast upgrade from the given old version
 * @package   block_opencast
 * @copyright 2017 Tamara Gunkel, WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

        // Catalog.
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
        $catalog[3]->param_json = '{"":"No option selected","slv":"Slovenian","por":"Portugese","roh":"Romansh","ara":"Arabic",' .
            '"pol":"Polish","ita":"Italian","zho":"Chinese","fin":"Finnish","dan":"Danish","ukr":"Ukrainian","fra":"French",' .
            '"spa":"Spanish","gsw":"Swiss German","nor":"Norwegian","rus":"Russian","jpx":"Japanese","nld":"Dutch",' .
            '"tur":"Turkish","hin":"Hindi","swa":"Swedish","eng":"English","deu":"German"}';

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
        $catalog[5]->param_json = '{"":"No option selected","ALLRIGHTS":"All Rights Reserved","CC0":"CC0","CC-BY-ND":"CC BY-ND",' .
            '"CC-BY-NC-ND":"CC BY-NC-ND","CC-BY-NC-SA":"CC BY-NC-SA","CC-BY-SA":"CC BY-SA","CC-BY-NC":"CC BY-NC","CC-BY":"CC BY"}';

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

    if ($oldversion < 2020040400) {
        // Define table block_opencast_ltimodule to be created.
        $table = new xmldb_table('block_opencast_ltimodule');

        // Adding fields to table block_opencast_ltimodule.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_ltimodule.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseid', XMLDB_KEY_UNIQUE, array('courseid'));
        $table->add_key('fk_course', XMLDB_KEY_FOREIGN_UNIQUE, array('courseid'), 'course', array('id'));
        $table->add_key('fk_cm', XMLDB_KEY_FOREIGN_UNIQUE, array('cmid'), 'course_modules', array('id'));

        // Conditionally launch create table for block_opencast_ltimodule.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Store new plugin settings' defaults to the DB (as the plugin does not use standard admin settings).
        set_config('addltienabled', '0', 'block_opencast');
        set_config('addltidefaulttitle', get_string('addlti_defaulttitle', 'block_opencast'), 'block_opencast');
        set_config('addltipreconfiguredtool', null, 'block_opencast');

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2020040400, 'opencast');
    }

    if ($oldversion < 2020072101) {

        // Define field chunkupload_presenter to be added to block_opencast_uploadjob.
        $table = new xmldb_table('block_opencast_uploadjob');
        $field = new xmldb_field('chunkupload_presenter', XMLDB_TYPE_CHAR, '15', null, null, null, null, 'timemodified');

        // Conditionally launch add field chunkupload_presenter.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('chunkupload_presentation', XMLDB_TYPE_CHAR, '15', null, null, null, null, 'timemodified');

        // Conditionally launch add field chunkupload_presentation.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2020072101, 'opencast');
    }

    if ($oldversion < 2020090701) {
        // Define table block_opencast_ltiepisode to be created.
        $table = new xmldb_table('block_opencast_ltiepisode');

        // Adding fields to table block_opencast_ltiepisode.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('episodeuuid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_ltiepisode.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('fk_course', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('fk_cm', XMLDB_KEY_FOREIGN_UNIQUE, array('cmid'), 'course_modules', array('id'));

        // Adding indexes to table block_opencast_ltiepisode.
        $table->add_index('episodeuuid', XMLDB_INDEX_NOTUNIQUE, array('episodeuuid'));

        // Conditionally launch create table for block_opencast_ltiepisode.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_opencast_ltimodule to be used.
        $table = new xmldb_table('block_opencast_ltimodule');

        // Here, we drop the 'courseid' unique key and the 'fk_course' foreign-unique key from the table block_opencast_ltimodule.
        // Afterwards, we recreate the 'fk_course' foreign-unique key.
        // This is done as a foreign-unique key produces a unique key automatically and we do not need two identical keys.
        // This is especially done in this way (dropping both keys, then recreating the second one from scratch) as it can happen
        // that Moodle drops both keys already when you just want to drop the first one as both are technically the same.

        // There is no key_exists, so test the equivalent index.
        $oldindex = new xmldb_index('courseid', XMLDB_KEY_UNIQUE, array('courseid'));

        // Launch drop key if the key exists.
        if ($dbman->index_exists($table, $oldindex)) {
            // Drop the key.
            $key = new xmldb_key('courseid', XMLDB_KEY_UNIQUE, array('courseid'));
            $dbman->drop_key($table, $key);
        }

        // There is no key_exists, so test the equivalent index.
        $oldindex2 = new xmldb_index('fk_course', XMLDB_KEY_UNIQUE, array('courseid'));

        // Launch drop key if the key exists.
        if ($dbman->index_exists($table, $oldindex2)) {
            // Drop the key.
            $key2 = new xmldb_key('fk_course', XMLDB_KEY_FOREIGN_UNIQUE, array('courseid'), 'course', array('id'));
            $dbman->drop_key($table, $key2);
        }

        // Launch add key if the key does not exist.
        if (!$dbman->index_exists($table, $oldindex2)) {
            $newkey = new xmldb_key('fk_course', XMLDB_KEY_FOREIGN_UNIQUE, array('courseid'), 'course', array('id'));
            $dbman->add_key($table, $newkey);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2020090701, 'opencast');
    }

    if ($oldversion < 2020111901) {
        // Define table block_opencast_ltiepisode_cu to be created.
        $table = new xmldb_table('block_opencast_ltiepisode_cu');

        // Adding fields to table block_opencast_ltiepisode_cu.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ocworkflowid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('queuecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_opencast_ltiepisode_cu.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('fk_course', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('fk_cm', XMLDB_KEY_FOREIGN_UNIQUE, array('cmid'), 'course_modules', array('id'));

        // Adding indexes to table block_opencast_ltiepisode_cu.
        $table->add_index('ocworkflowid', XMLDB_INDEX_NOTUNIQUE, array('ocworkflowid'));

        // Conditionally launch create table for block_opencast_ltiepisode_cu.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2020111901, 'opencast');
    }

    if ($oldversion < 2021051200) {

        // Define table block_opencast_roles to be dropped.
        $table = new xmldb_table('block_opencast_roles');

        if ($dbman->table_exists($table)) {
            // Write existing data to config.
            $records = array_map(function ($r) {
                unset($r->id);
                $r->permanent = $r->permanent ? 1 : 0;
                return $r;
            }, array_values($DB->get_records('block_opencast_roles')));

            $config = json_encode($records);
            set_config('roles', $config, 'block_opencast');

            // Drop table.
            $dbman->drop_table($table);
        }

        // Define table block_opencast_catalog to be dropped.
        $table = new xmldb_table('block_opencast_catalog');

        if ($dbman->table_exists($table)) {
            // Write existing data to config.
            $config = json_encode(array_map(function ($r) {
                unset($r->id);
                $r->required = $r->required ? 1 : 0;
                $r->readonly = $r->readonly ? 1 : 0;
                return $r;
            }, array_values($DB->get_records('block_opencast_catalog'))));
            set_config('metadata', $config, 'block_opencast');

            // Drop table.
            $dbman->drop_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021051200, 'opencast');
    }

    if ($oldversion < 2021061600) {
        // Delete dangling .dot files.

        $params = [
            'component' => 'block_opencast',
            'filearea' => 'videotoupload'
        ];

        $sql = "SELECT CONCAT(contextid, '_', itemid), contextid, itemid, COUNT(*) as cnt " .
            "FROM {files} " .
            "WHERE component = :component " .
            "AND filearea = :filearea GROUP BY contextid, itemid;";

        if ($entries = $DB->get_records_sql($sql, $params)) {
            foreach ($entries as $entry) {
                if ($entry->cnt === "1") {
                    // Only .dot file left. Delete it.
                    $params = [
                        'component' => 'block_opencast',
                        'filearea' => 'videotoupload',
                        'contenthash' => 'da39a3ee5e6b4b0d3255bfef95601890afd80709',
                        'filename' => '.',
                        'contextid' => $entry->contextid,
                        'itemid' => $entry->itemid
                    ];

                    $sql = "SELECT f.* " .
                        "FROM {files} f " .
                        "WHERE f.contenthash = :contenthash AND f.component = :component " .
                        "AND f.filearea = :filearea AND f.filename = :filename AND f.itemid = :itemid AND f.contextid = :contextid";

                    if (!$dotfiles = $DB->get_records_sql($sql, $params)) {
                        continue;
                    }

                    $fs = get_file_storage();
                    foreach ($dotfiles as $dotfile) {
                        $fs->get_file_instance($dotfile)->delete();
                    }
                }
            }
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021061600, 'opencast');
    }

    if ($oldversion < 2021062401) {

        // Define table block_opencast_series to be dropped.
        $table = new xmldb_table('block_opencast_series');

        if ($dbman->table_exists($table)) {
            // Drop table.
            $dbman->drop_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021062401, 'opencast');
    }

    if ($oldversion < 2021072000) {
        // Define settings fields so that update can be executed multiple times without problems.
        $settingsfields = ['roles', 'metadata', 'limituploadjobs', 'uploadworkflow', 'publishtoengage',
            'reuseexistingupload', 'allowunassign', 'deleteworkflow', 'adhocfiledeletion', 'uploadfileextensions',
            'limitvideos', 'group_creation', 'group_name', 'series_name', 'workflow_roles',
            'showpublicationchannels', 'showenddate', 'showlocation', 'enablechunkupload', 'uploadfilelimit',
            'offerchunkuploadalternative', 'enable_opencast_studio_link', 'lticonsumerkey', 'lticonsumersecret',
            'aclcontrolafter', 'aclcontrolgroup', 'addactivityenabled', 'addactivitydefaulttitle', 'addactivityintro',
            'addactivitysection', 'addactivityavailability', 'addactivityepisodeenabled', 'addactivityepisodeintro',
            'addactivityepisodesection', 'addactivityepisodeavailability', 'download_channel', 'workflow_tag',
            'support_email', 'termsofuse', 'addltienabled', 'addltidefaulttitle', 'addltipreconfiguredtool',
            'addltiintro', 'addltisection', 'addltiavailability', 'addltiepisodeenabled',
            'addltiepisodepreconfiguredtool', 'addltiepisodeintro', 'addltiepisodesection', 'addltiepisodeavailability',
            'importvideosenabled', 'duplicateworkflow', 'importvideoscoreenabled', 'importvideosmanualenabled',
            'importvideoshandleseriesenabled', 'importvideoshandleepisodeenabled'];

        $fieldsjoined = "('" . implode("','", $settingsfields) . "')";

        // Check if settings were upgraded without upgrading the plugin.
        if ($DB->get_record('config_plugins', array('plugin' => 'block_opencast', 'name' => 'roles')) &&
            $DB->get_record('config_plugins', array('plugin' => 'block_opencast', 'name' => 'roles_1'))) {
            // Remove already upgraded settings and only keep old ones.
            $DB->execute("DELETE FROM {config_plugins} WHERE plugin='block_opencast' AND name != 'version' " .
                "AND name not in " . $fieldsjoined);
        }

        // Update configs to use default tenant (id=1).
        $DB->execute("UPDATE {config_plugins} SET name=CONCAT(name,'_1') WHERE plugin='block_opencast' " .
            "AND name in " . $fieldsjoined);

        $dbtables = ['block_opencast_uploadjob', 'block_opencast_deletejob', 'block_opencast_groupaccess',
            'block_opencast_ltimodule', 'block_opencast_ltiepisode', 'block_opencast_ltiepisode_cu'];

        foreach ($dbtables as $dbtable) {
            // Add new opencast instance field.
            $table = new xmldb_table($dbtable);
            $field = new xmldb_field('ocinstanceid', XMLDB_TYPE_INTEGER, '10');

            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $DB->set_field($dbtable, 'ocinstanceid', 1);

            $field = new xmldb_field('ocinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $dbman->change_field_notnull($table, $field);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021072000, 'opencast');
    }

    if ($oldversion < 2021073101) {
        if (get_config('tool_opencast', 'version') < 2021091200) {
            // Required version is not fulfilled. Extra check needed because moodle does not do it sufficiently.
            throw new moodle_exception('tool_requirement_not_fulfilled', 'block_opencast');
        }

        // Define field seriesid to be added to block_opencast_ltimodule.
        $table = new xmldb_table('block_opencast_ltimodule');
        $field = new xmldb_field('seriesid', XMLDB_TYPE_CHAR, '36', null, null, null, null, 'cmid');

        // Conditionally launch add field seriesid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            $DB->execute("UPDATE {block_opencast_ltimodule} SET seriesid=(SELECT ts.series " .
                "FROM {tool_opencast_series} ts WHERE ts.isdefault=1 AND " .
                "ts.courseid={block_opencast_ltimodule}.courseid AND " .
                "ts.ocinstanceid={block_opencast_ltimodule}.ocinstanceid)");

            $dbman->change_field_notnull($table, $field);
        }

        // Allow course id to occur multiple times.
        $key = new xmldb_key('fk_course', XMLDB_KEY_FOREIGN_UNIQUE, array('courseid'), 'course', array('id'));
        $dbman->drop_key($table, $key);
        $table->add_key('fk_course', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021073101, 'opencast');
    }

    if ($oldversion < 2021091200) {
        // Define table block_opencast_notifications to be created.
        $table = new xmldb_table('block_opencast_notifications');

        // Adding fields to table block_opencast_notifications.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ocinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('opencasteventid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('notified', XMLDB_TYPE_INTEGER, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timenotified', XMLDB_TYPE_INTEGER, '10');

        // Adding keys to table block_opencast_notifications.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_opencast_notifications.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021091200, 'opencast');
    }

    if ($oldversion < 2021110300) {
        $table = new xmldb_table('block_opencast_groupaccess');

        $field = new xmldb_field('groups', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'moodlegroups');
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021110300, 'opencast');
    }

    if ($oldversion < 2021113000) {

        // Define field mediapackage to be added to block_opencast_uploadjob.
        $table = new xmldb_table('block_opencast_uploadjob');
        $field = new xmldb_field('mediapackage', XMLDB_TYPE_TEXT, null, null, null, null, null, 'chunkupload_presentation');

        // Conditionally launch add field mediapackage.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2021113000, 'opencast');
    }

    if ($oldversion < 2022012500) {
        // Define table block_opencast_visibility to be created.
        $table = new xmldb_table('block_opencast_visibility');

        // Adding fields to table block_opencast_visibility.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('uploadjobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('initialvisibilitystatus', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL);
        $table->add_field('initialvisibilitygroups', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('scheduledvisibilitytime', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('scheduledvisibilitystatus', XMLDB_TYPE_INTEGER, '1');
        $table->add_field('scheduledvisibilitygroups', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL);

        // Adding keys to table block_opencast_visibility.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uploadjob_foreign', XMLDB_KEY_FOREIGN, ['uploadjobid'], 'block_opencast_uploadjob', ['id']);

        // Conditionally launch create table for block_opencast_visibility.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2022012500, 'opencast');
    }

    if ($oldversion < 2022022200) {

        // Define field id to be added to block_opencast_user_default.
        $table = new xmldb_table('block_opencast_user_default');

        // Adding fields to table block_opencast_user_default.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('defaults', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'userid');

        // Adding keys to table block_opencast_user_default.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for block_opencast_user_default.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2022022200, 'opencast');
    }

    if ($oldversion < 2022100400) {
        // Update workflow_tag configs to workflow_tags.
        $DB->execute("UPDATE {config_plugins} SET name = REPLACE(name, 'workflow_tag', 'workflow_tags') " .
            "WHERE plugin='block_opencast' AND name LIKE ('workflow_tag%')");

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2022100400, 'opencast');
    }

    if ($oldversion < 2022102200) {
        // Here to change the visibility table structure to handle the edit/delete/add new operations.
        $table = new xmldb_table('block_opencast_visibility');
        // Define key uploadjob_foreign (foreign) to be dropped form block_opencast_visibility.
        $key = new xmldb_key('uploadjob_foreign', XMLDB_KEY_FOREIGN, ['uploadjobid'], 'block_opencast_uploadjob', ['id']);

        // Launch drop key uploadjob_foreign.
        $dbman->drop_key($table, $key);

        // Changing nullability of field uploadjobid on table block_opencast_visibility to null.
        $field = new xmldb_field('uploadjobid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');

        // Launch change of nullability for field uploadjobid.
        $dbman->change_field_notnull($table, $field);

        // Define field opencasteventid to be added to block_opencast_visibility.
        $field = new xmldb_field('opencasteventid', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'uploadjobid');

        // Conditionally launch add field opencasteventid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field ocinstanceid to be added to block_opencast_visibility.
        $field = new xmldb_field('ocinstanceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'opencasteventid');

        // Conditionally launch add field ocinstanceid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field courseid to be added to block_opencast_visibility.
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'ocinstanceid');

        // Conditionally launch add field courseid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2022102200, 'opencast');
    }
    if ($oldversion < 2022101800) {
        // Define table block_opencast_attachments to be created.
        $table = new xmldb_table('block_opencast_attachments');

        // Adding fields to table block_opencast_attachments.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('uploadjobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('files', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table block_opencast_visibility.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uploadjob_foreign', XMLDB_KEY_FOREIGN, ['uploadjobid'], 'block_opencast_uploadjob', ['id']);

        // Conditionally launch create table for block_opencast_visibility.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opencast savepoint reached.
        upgrade_block_savepoint(true, 2022101800, 'opencast');
    }

    return true;
}
