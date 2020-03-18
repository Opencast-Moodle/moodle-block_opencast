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
    $record[0]->permanent = true;

    $record[1] = new \stdClass();
    $record[1]->rolename = 'ROLE_GROUP_MH_DEFAULT_ORG_EXTERNAL_APPLICATIONS';
    $record[1]->actions = 'write,read';
    $record[1]->permanent = true;

    $record[2] = new \stdClass();
    $record[2]->rolename = '[COURSEID]_Instructor';
    $record[2]->actions = 'write,read';
    $record[2]->permanent = true;

    $record[3] = new \stdClass();
    $record[3]->rolename = '[COURSEGROUPID]_Learner';
    $record[3]->actions = 'read';
    $record[3]->permanent = false;

    // Insert new record.
    $DB->insert_records('block_opencast_roles', $record);

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
}

