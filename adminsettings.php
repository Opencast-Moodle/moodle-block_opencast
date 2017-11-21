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
 * @package block_evasys_sync
 * @copyright 2017 Tamara Gunkel
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');;
$delrole = optional_param('d', 0, PARAM_INT);
require_login();

// Set the URL that should be used to return to this page.
$PAGE->set_url('/blocks/opencast/adminsettings');

if (has_capability('moodle/site:config', context_system::instance())) {
    admin_externalpage_setup('block_opencast');

    $mform = new block_opencast\admin_form();

    if (!empty($delrole)) {
        // Role is deleted.
        $DB->delete_records('block_opencast_roles', array('id' => $delrole));
        redirect($PAGE->url);
        exit();
    }

    if ($data = $mform->get_data()) {
        // Form is submitted.
        // Added course category.
        if (isset($data->addrolebutton)) {
            $record = new \stdClass();
            $record->rolename = $data->rolename;
            $record->actionname = $data->actionname;

            // Insert new record.
            $DB->insert_record('block_opencast_roles', $record, false);
            redirect($PAGE->url);
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
            if (isset($data->group_creation)) {
                set_config('group_creation', $data->group_creation, 'block_opencast');
            }
            if (isset($data->group_name)) {
                set_config('group_name', $data->group_name, 'block_opencast');
            }
            if (isset($data->series_name)) {
                set_config('series_name', $data->series_name, 'block_opencast');
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

    // Section API settings
    $entry->apiurl = get_config('block_opencast', 'apiurl');
    $entry->apiusername = get_config('block_opencast', 'apiusername');
    $entry->apipassword = get_config('block_opencast', 'apipassword');
    $entry->connecttimeout = get_config('block_opencast', 'connecttimeout');

    // Section cron settings
    $entry->limituploadjobs = get_config('block_opencast', 'limituploadjobs');
    $entry->uploadworkflow = get_config('block_opencast', 'uploadworkflow');
    $entry->publishtoengage = get_config('block_opencast', 'puplishtoengage');
    $entry->reuseexistingupload = get_config('block_opencast', 'reuseexistingupload');

    // Section overview settings
    $entry->limitvideos = get_config('block_opencast', 'limitvideos');

    // Section access policies
    $entry->group_creation = get_config('block_opencast', 'group_creation');
    $entry->group_name = get_config('block_opencast', 'group_name');
    $entry->series_name = get_config('block_opencast', 'series_name');


    $mform->set_data($entry);
    $mform->display();
    echo $OUTPUT->footer();
}
