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
            if (isset($data->apiurl)) {
                set_config('apiurl', $data->apiurl, 'block_opencast');
            }
            if (isset($data->apiusername)) {
                set_config('apiusername', $data->apiusername, 'block_opencast');
            }
            if (isset($data->apipassword)) {
                set_config('apipassword', $data->apipassword, 'block_opencast');
            }
            if (isset($data->connecttimeout)) {
                set_config('connecttimeout', $data->connecttimeout, 'block_opencast');
            }
            if (isset($data->limituploadjobs)) {
                set_config('limituploadjobs', $data->limituploadjobs, 'block_opencast');
            }
            if (isset($data->uploadfilelimit)) {
                set_config('uploadfilelimit', $data->uploadfilelimit, 'block_opencast');
            }
            if (isset($data->uploadworkflow)) {
                set_config('uploadworkflow', $data->uploadworkflow, 'block_opencast');
            }
            if (isset($data->publishtoengage)) {
                set_config('publishtoengage', $data->publishtoengage, 'block_opencast');
            }
            if (isset($data->reuseexistingupload)) {
                set_config('reuseexistingupload', $data->reuseexistingupload, 'block_opencast');
            }
            if (isset($data->limitvideos)) {
                set_config('limitvideos', $data->limitvideos, 'block_opencast');
            }
            if (isset($data->showpublicationchannels)) {
                set_config('showpublicationchannels', $data->showpublicationchannels, 'block_opencast');
            }
            if (isset($data->group_creation)) {
                set_config('group_creation', $data->group_creation, 'block_opencast');
            }
            if (isset($data->group_name)) {
                set_config('group_name', $data->group_name, 'block_opencast');
            }
            if (isset($data->series_name)) {
                set_config('series_name', $data->series_name, 'block_opencast');
            }
            if (isset($data->workflow_roles)) {
                set_config('workflow_roles', $data->workflow_roles, 'block_opencast');
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

    // Section API settings.
    $entry->apiurl = get_config('block_opencast', 'apiurl');
    $entry->apiusername = get_config('block_opencast', 'apiusername');
    $entry->apipassword = get_config('block_opencast', 'apipassword');
    $entry->connecttimeout = get_config('block_opencast', 'connecttimeout');

    // Section cron settings.
    $entry->limituploadjobs = get_config('block_opencast', 'limituploadjobs');
    $entry->uploadfilelimit = get_config('block_opencast', 'uploadfilelimit');
    $entry->uploadworkflow = get_config('block_opencast', 'uploadworkflow');
    $entry->publishtoengage = get_config('block_opencast', 'publishtoengage');
    $entry->reuseexistingupload = get_config('block_opencast', 'reuseexistingupload');

    // Section overview settings.
    $entry->limitvideos = get_config('block_opencast', 'limitvideos');
    $entry->showpublicationchannels = get_config('block_opencast', 'showpublicationchannels');

    // Section access policies.
    $entry->group_creation = get_config('block_opencast', 'group_creation');
    $entry->group_name = get_config('block_opencast', 'group_name');
    $entry->series_name = get_config('block_opencast', 'series_name');

    // Section roles.
    $entry->workflow_roles = get_config('block_opencast', 'workflow_roles');

    $mform->set_data($entry);
    $mform->display();
    echo $OUTPUT->footer();
}
