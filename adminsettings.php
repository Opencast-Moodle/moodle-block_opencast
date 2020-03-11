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
$delcatalog = optional_param('cd', 0, PARAM_INT);
$delattachmentfield = optional_param('afd', 0, PARAM_INT);
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
        'uploadfileextensions',
        'limitvideos',
        'showpublicationchannels',
        'duplicateworkflow',
        'group_creation',
        'group_name',
        'series_name',
        'workflow_roles',
        'workflow_attachments',
    ];

    if (!empty($delrole) && !empty($confirm)) {
        // Role is deleted.
        $DB->delete_records('block_opencast_roles', array('id' => $delrole));
        redirect($PAGE->url . '#id_roles_header');
        exit();
    }

    if (!empty($delcatalog) && !empty($confirm)) {
        // Catalog is deleted.
        $DB->delete_records('block_opencast_catalog', array('id' => $delcatalog));
        redirect($PAGE->url . '#id_catalog_header');
        exit();
    }

    if (!empty($delattachmentfield) && !empty($confirm)) {
        // Catalog is deleted.
        $DB->delete_records('block_opencast_attach_field', ['id' => $delattachmentfield]);
        redirect($PAGE->url . '#id_attachments_header');
        exit();
    }
    
    if (!empty($delrole)) {
        // Deletion has to be confirmed.
        // Print a confirmation message.
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('settings', 'block_opencast'));
        echo $OUTPUT->confirm(get_string("delete_confirm_role", 'block_opencast'),
            "adminsettings.php?d=$delrole&c=$delrole",
            'adminsettings.php');
        echo $OUTPUT->footer();
        exit();
    } else if (!empty($delcatalog)) {
        // Deletion has to be confirmed.
        // Print a confirmation message.
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('settings', 'block_opencast'));
        echo $OUTPUT->confirm(get_string("delete_confirm_catalog", 'block_opencast'),
            "adminsettings.php?cd=$delcatalog&c=$delcatalog",
            'adminsettings.php');
        echo $OUTPUT->footer();
        exit();
    } else if (!empty($delattachmentfield)) {
        // Deletion has to be confirmed.
        // Print a confirmation message.
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('settings', 'block_opencast'));
        echo $OUTPUT->confirm(get_string("delete_confirm_attachmentfield", 'block_opencast'),
            "adminsettings.php?afd=$delattachmentfield&c=$delattachmentfield",
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
        } else if (isset($data->addcatalogbutton)) { //Adding new Metadata Catalog
            $ret = get_string('addnewcatalogfield', 'block_opencast');
            $notify =  \core\notification::SUCCESS;
            if (trim($data->catalogname)) {
                $newcatalog = new \stdClass();
                $newcatalog->name = str_replace(' ', '',  strtolower($data->catalogname));
                $newcatalog->datatype = $data->catalogreadonly  == 1 ? 'static' : $data->catalogdatatype;
                $newcatalog->required = $data->catalogreadonly  == 1 ? 0 : ($data->catalogrequired == 1 ? 1 : 0) ;
                $newcatalog->readonly = $data->catalogreadonly  == 1 ? 1 : 0;
                $newcatalog->param_json = $data->catalogparam;
                
                if ( !$DB->record_exists('block_opencast_catalog', array('name'=> $newcatalog->name)) ) {
                    $DB->insert_record('block_opencast_catalog', $newcatalog, false);
                } else {
                    $ret = get_string( 'exists_catalogname', 'block_opencast' );
                    $notify =  \core\notification::ERROR;
                }
                // Insert new record.
            } else {
                $ret = get_string( 'empty_catalogname', 'block_opencast' );
                $notify =  \core\notification::ERROR;
            }
            
            redirect($PAGE->url . '#id_catalog_header', $ret, null, $notify);
            exit();
        } else if (isset($data->addattachmentfieldbutton)) { //Adding new Attachment Field
            $ret = get_string('addnewattachmentfield', 'block_opencast');
            $notify =  \core\notification::SUCCESS;
            if (trim($data->attachmentfieldname)) {
                $newattachmentfield = new \stdClass();
                $newattachmentfield->name = str_replace(' ', '',  strtolower($data->attachmentfieldname));
                $newattachmentfield->required = $data->attachmentfieldrequired == 1 ? 1 : 0;
                $newattachmentfield->asset_id = $data->attachmentfieldassetid;
                $newattachmentfield->type = $data->attachmentfieldtype;
                $newattachmentfield->flavor_type = $data->attachmentfieldflavortype;
                $newattachmentfield->flavor_subtype = $data->attachmentfieldflavorsubtype;
                $newattachmentfield->filetypes = $data->attachmentfieldfiletypes;
                
                if ( !$DB->record_exists('block_opencast_attach_field', ['name'=> $newattachmentfield->name]) ) {
                    $DB->insert_record('block_opencast_attach_field', $newattachmentfield, false);
                } else {
                    $ret = get_string( 'exists_attachmentfieldname', 'block_opencast' );
                    $notify =  \core\notification::ERROR;
                }
                // Insert new record.
            } else {
                $ret = get_string( 'empty_attachmentfieldname', 'block_opencast' );
                $notify =  \core\notification::ERROR;
            }
            
            redirect($PAGE->url . '#id_attachments_header', $ret, null, $notify);
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

            //Update Metadata Catalog
            $catalogs = $DB->get_records('block_opencast_catalog');
            foreach ($catalogs as $catalog) {
                $catalog_name = "catalog_name_{$catalog->id}";
                $catalog_datatype = "catalog_datatype_{$catalog->id}";
                $catalog_required = "catalog_required_{$catalog->id}";
                $catalog_readonly = "catalog_readonly_{$catalog->id}";
                $catalog_params = "catalog_params_{$catalog->id}";

                $newcatalog = new \stdClass();
                $newcatalog->id = $catalog->id;
                $newcatalog->name = $data->$catalog_name;
                $newcatalog->datatype = $data->$catalog_readonly == 1 ? 'static' : $data->$catalog_datatype;
                $newcatalog->required = $data->$catalog_readonly == 1 ? 0 : ($data->$catalog_required == 1 ? 1 : 0) ;
                $newcatalog->readonly = $data->$catalog_readonly == 1 ? 1 : 0 ;
                $newcatalog->param_json = $data->$catalog_params;

                // Update db entry.
                if ($newcatalog->name !== $catalog->name ||
                    $newcatalog->datatype !== $catalog->datatype ||
                    $newcatalog->required != $catalog->required ||
                    $newcatalog->readonly != $catalog->readonly ||
                    $newcatalog->param_json !== $catalog->param_json
                 ) {
                    $DB->update_record('block_opencast_catalog', $newcatalog);
                }
            }

            //Update Attachment Fields
            $attachmentfields = $DB->get_records('block_opencast_attach_field');
            foreach ($attachmentfields as $attachmentfield) {
                $attachmentfield_name = "attachmentfield_name_{$attachmentfield->id}";
                $attachmentfield_required = "attachmentfield_required_{$attachmentfield->id}";
                $attachmentfield_assetid = "attachmentfield_assetid_{$attachmentfield->id}";
                $attachmentfield_type = "attachmentfield_type_{$attachmentfield->id}";
                $attachmentfield_flavortype = "attachmentfield_flavortype_{$attachmentfield->id}";
                $attachmentfield_flavorsubtype = "attachmentfield_flavorsubtype_{$attachmentfield->id}";
                $attachmentfield_filetypes = "attachmentfield_filetypes_{$attachmentfield->id}";

                $newattachmentfield = new \stdClass();
                $newattachmentfield->id = $attachmentfield->id;
                $newattachmentfield->name = $data->$attachmentfield_name;
                $newattachmentfield->required = $data->$attachmentfield_required == 1 ? 1 : 0;
                $newattachmentfield->assetid = $data->$attachmentfield_assetid;
                $newattachmentfield->type = $data->$attachmentfield_type;
                $newattachmentfield->flavortype = $data->$attachmentfield_flavortype;
                $newattachmentfield->flavorsubtype = $data->$attachmentfield_flavorsubtype;
                $newattachmentfield->filetypes = $data->$attachmentfield_filetypes;

                // Update db entry.
                if ($newattachmentfield->name !== $attachmentfield->name ||
                    $newattachmentfield->required != $attachmentfield->required ||
                    $newattachmentfield->assetid != $attachmentfield->asset_id ||
                    $newattachmentfield->type != $attachmentfield->type ||
                    $newattachmentfield->flavortype != $attachmentfield->flavor_type ||
                    $newattachmentfield->flavorsubtype != $attachmentfield->flavor_subtype ||
                    $newattachmentfield->filetypes !== $attachmentfield->filetypes
                 ) {
                    $DB->update_record('block_opencast_attach_field', $newattachmentfield);
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
