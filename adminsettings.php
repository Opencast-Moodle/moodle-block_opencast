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
 * Settings for the opencast block
 *
 * @package   block_opencast
 * @copyright 2017 Tamara Gunkel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');;
$delrole = optional_param('d', 0, PARAM_INT);
$confirm = optional_param('c', 0, PARAM_INT);
require_login();

// Set the URL that should be used to return to this page.
$PAGE->set_url('/blocks/opencast/adminsettings');

if (has_capability('moodle/site:config', context_system::instance())) {
    admin_externalpage_setup('block_opencast');

    $mform = new block_opencast\admin_form();

    $settingsfields = [
        'limituploadjobs',
        'uploadworkflow',
        'publishtoengage',
        'reuseexistingupload',
        'allowunassign',
        'deleteworkflow',
        'adhocfiledeletion',
        'limitvideos',
        'showpublicationchannels',
        'group_creation',
        'group_name',
        'series_name',
        'workflow_roles',
    ];

    if (!empty($delrole) && !empty($confirm)) {
        // Role is deleted.
        $DB->delete_records('block_opencast_roles', array('id' => $delrole));
        redirect($PAGE->url . '#id_roles_header');
        exit();
    }

    if (!empty($delrole)) {
        // Deletion has to be confirmed.
        // Print a confirmation message.
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('settings', 'block_opencast'));
        echo $OUTPUT->confirm(get_string("delete_confirm", 'block_opencast'),
            "adminsettings.php?d=$delrole&c=$delrole",
            'adminsettings.php');
        echo $OUTPUT->footer();
        exit();
    } else if ($data = $mform->get_data()) {
        // Form is submitted.
        // Added course category.
        if (isset($data->addrolebutton)) {
            $record = new \stdClass();
            $record->rolename = $data->rolename;
            $record->actions = $data->actions;
            $record->permanent = $data->permanent;

            // Insert new record.
            $DB->insert_record('block_opencast_roles', $record, false);
            redirect($PAGE->url . '#id_roles_header');
            exit();
        } else if (isset($data->submitbutton)) {

            foreach ($settingsfields as $field) {
                if (isset($data->$field)) {
                    set_config($field, $data->$field, 'block_opencast');
                }
            }

            // Update roles.
            $roles = $DB->get_records('block_opencast_roles');
            foreach ($roles as $role) {
                $rname = 'role_'.$role->id;
                $aname = 'action_'.$role->id;
                $pname = 'permanent_'.$role->id;

                // Update db entry.
                if ($data->$rname !== $role->rolename || $data->$aname !== $role->actions || $data->$pname !== $role->permanent) {
                    $record = new \stdClass();
                    $record->id = $role->id;
                    $record->rolename = $data->$rname;
                    $record->actions = $data->$aname;
                    $record->permanent = $data->$pname;

                    $DB->update_record('block_opencast_roles', $record);
                }
            }
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('settings', 'block_opencast'));

    // Load existing setttings.
    if (empty($entry->id)) {
        $entry = new stdClass;
        $entry->id = 0;
    }

    foreach ($settingsfields as $field) {
        $config = get_config('block_opencast', $field);
        if ($config !== false) {
            $entry->$field = $config;
        }
    }

    $mform->set_data($entry);
    $mform->display();
    echo $OUTPUT->footer();
}
