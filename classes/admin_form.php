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
 * Opencast block admin form.
 *
 * @package   block_opencast
 * @copyright 2017 Tamara Gunkel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use moodleform;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

class admin_form extends moodleform {
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Section cron settings.
        $mform->addElement('header', 'cron_header', get_string('cronsettings', 'block_opencast'));

        // Limit upload jobs.
        $url = new moodle_url('/admin/tool/task/scheduledtasks.php');
        $link = html_writer::link($url, get_string('pluginname', 'tool_task'), array('target' => '_blank'));
        $name = 'limituploadjobs';
        $title = get_string('limituploadjobs', 'block_opencast');
        $description = get_string('limituploadjobsdesc', 'block_opencast', $link);
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 1);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Upload workflow.
        $name = 'uploadworkflow';
        $title = get_string('uploadworkflow', 'block_opencast');
        $description = get_string('uploadworkflowdesc', 'block_opencast');
        $mform->addElement('select', $name, $title, $apibridge->get_existing_workflows('upload'));
        $mform->setType($name, PARAM_TEXT);
        $mform->setDefault($name, 'ng-schedule-and-upload');
        $mform->addElement('static', 'description' . $name, '', $description);

        // Publish to engage.
        $name = 'publishtoengage';
        $title = get_string('publishtoengage', 'block_opencast');
        $description = get_string('publishtoengagedesc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 0);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Reuse existing upload.
        $name = 'reuseexistingupload';
        $title = get_string('reuseexistingupload', 'block_opencast');
        $description = get_string('reuseexistinguploaddesc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 0);
        $mform->addElement('static', 'description' . $name, '', $description);

         // Allow unassign.
        $name = 'allowunassign';
        $title =   get_string('allowunassign', 'block_opencast');
        $description =    get_string('allowunassigndesc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 0);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Delete workflow.
        $noworkflow = [null => get_string("adminchoice_noworkflow", "block_opencast")];
        $name = 'deleteworkflow';
        $title = get_string('deleteworkflow', 'block_opencast');
        $description = get_string('deleteworkflowdesc', 'block_opencast');
        $mform->addElement('select', $name, $title, array_merge($noworkflow,
            $apibridge->get_existing_workflows('delete')));
        $mform->setType($name, PARAM_TEXT);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Configurate, whether a videofile should be deleted from moodle's filesystem
        // right after the file was transferred (uploaded) to opencast server.
        // The plugin deletes all files in users draft area, which are related to
        // uploaded video and removes the video file from trash also.
        $name = 'adhocfiledeletion';
        $title =   get_string('adhocfiledeletion', 'block_opencast');
        $description =    get_string('adhocfiledeletiondesc', 'block_opencast');
        $mform->addElement('selectyesno', $name, $title);
        $mform->setDefault($name, 0);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Supported file extensions.
        $name = 'uploadfileextensions';
        $title = get_string('uploadfileextensions', 'block_opencast');
        $description = get_string('uploadfileextensionsdesc', 'block_opencast', $CFG->wwwroot.'/admin/tool/filetypes/index.php');
        $mform->addElement('filetypes', $name, $title);
        $mform->setDefault($name, '');
        $mform->addElement('static', 'description' . $name, '', $description);

        // Section overview settings.
        $mform->addElement('header', 'block_header', get_string('blocksettings', 'block_opencast'));

        // Limit videos.
        $name = 'limitvideos';
        $title = get_string('limitvideos', 'block_opencast');
        $description = get_string('limitvideosdesc', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 5);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Show publication channels.
        $name = "showpublicationchannels";
        $title = get_string('show_public_channels', 'block_opencast');
        $description = get_string('show_public_channels_desc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 1);
        $mform->addElement('static', 'description'. $name, '', $description);

        // Section overview settings.
        $mform->addElement('header', 'backuprestore_header', get_string('backupsettings', 'block_opencast'));
        $name = 'duplicateworkflow';
        $title = get_string('duplicateworkflow', 'block_opencast');
        $description = get_string('duplicateworkflowdesc', 'block_opencast');
        $mform->addElement('select', $name, $title, array_merge($noworkflow,
            $apibridge->get_existing_workflows('api')));
        $mform->setType($name, PARAM_TEXT);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Section access policies.
        $mform->addElement('header', 'groupseries_header', get_string('groupseries_header', 'block_opencast'));

        // Group creation.
        $name = 'group_creation';
        $title = get_string('groupcreation', 'block_opencast');
        $description = get_string('groupcreationdesc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 0);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Group name.
        $name = 'group_name';
        $title = get_string('groupname', 'block_opencast');
        $description = get_string('groupnamedesc', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);
        $mform->setDefault($name, 'Moodle_course_[COURSEID]');
        $mform->addElement('static', 'description' . $name, '', $description);

        // Series name.
        $name = 'series_name';
        $title = get_string('seriesname', 'block_opencast');
        $description = get_string('seriesnamedesc', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);
        $mform->setDefault($name, 'Course_Series_[COURSEID]');
        $mform->addElement('static', 'description' . $name, '', $description);

        // Acl roles.
        $mform->addElement('header', 'roles_header', get_string('aclrolesname', 'block_opencast'));
        $mform->setExpanded('roles_header');

        // Workflow adding/deleting non-permanent roles.
        $name = 'workflow_roles';
        $title = get_string('workflowrolesname', 'block_opencast');
        $description = get_string('workflowrolesdesc', 'block_opencast');
        $mform->addElement('select', $name, $title, array_merge($noworkflow,
            $apibridge->get_existing_workflows('archive')));
        $mform->setType($name, PARAM_TEXT);
        $mform->addElement('static', 'description' . $name, '', $description);

        // New role name.
        $name = 'rolename';
        $title = get_string('rolename', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);

        // New role action.
        $name = 'actions';
        $title = get_string('actions', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);

        // New role visibility.
        $name = 'permanent';
        $title = get_string('setting_permanent', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 1);

        $mform->addElement('static', 'descriptionacl', '', get_string('aclrolesnamedesc', 'block_opencast'));

        // Add Button.
        $mform->addElement('submit', 'addrolebutton', get_string('addrole', 'block_opencast'));

        // Add Table.
        $mform->addElement('html', $this->tablehead());
        $this->table_body();
        //End Acl Roles

        // Metadata Catalog Setting
        $mform->addElement('header', 'catalog_header', get_string('metadata', 'block_opencast'));
        $mform->setExpanded('catalog_header');
        // New catalog name.
        $name = 'catalogname';
        $title = get_string('heading_name', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);
        $mform->addElement('static', 'descriptionmdfn', '', get_string('descriptionmdfn', 'block_opencast'));

        // New catalog datatype.
        $name = 'catalogdatatype';
        $title = get_string('heading_datatype', 'block_opencast');
        $datatypes = [
            'text' => 'String (text)',
            'select' => 'Drop Down (select)',
            'autocomplete' => 'Arrays (autocomplete)',
            'textarea' => 'Long Text (textarea)',
            'date_time_selector' => 'Date Time Selector (datetime)'
        ];
        $mform->addElement('select', $name, $title, $datatypes);
        $mform->setType($name, PARAM_TEXT);
        $mform->disabledIf($name, 'catalogreadonly', 'checked');

        // New catalog Required.
        $name = 'catalogrequired';
        $title = get_string('heading_required', 'block_opencast');
        $mform->addElement('checkbox', $name, $title);
        $mform->disabledIf($name, 'catalogreadonly', 'checked');

        // New catalog ReadOnly.
        $name = 'catalogreadonly';
        $title = get_string('heading_readonly', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 0);

        // New catalog param json.
        $name = 'catalogparam';
        $title = get_string('catalogparam', 'block_opencast');
        $mform->addElement('textarea', $name, $title);
        $mform->addHelpButton($name, $name, 'block_opencast');
        $mform->setType($name, PARAM_TEXT);
        $mform->addElement('static', 'descriptionmdpj', '', get_string('descriptionmdpj', 'block_opencast'));

        $mform->addElement('submit', 'addcatalogbutton', get_string('addcatalog', 'block_opencast'));
        // Add Table.
        $mform->addElement('html', $this->tablehead('catalog'));
        $this->table_body('catalog');
        // End Metadata Catalog

        $mform->addElement('submit', 'submitbutton', get_string('submit', 'block_opencast'));
    }

    /**
     * Prints the table head (e.g. column names).
     * @return string
     */
    public function tablehead($field = 'role') {
        $table_attributes = array();
        $th_attributes = array();
        if ($field == 'role') {

            $table_attributes = [
                'class' => 'generaltable',
                'id' => 'roles_table'
            ];

            $th_attributes = [
                'heading_role' => [
                    'class' => 'header c0',
                    'scope' => 'col'
                ],
                'heading_actions' => [
                    'class' => 'header c1',
                    'scope' => 'col'
                ],
                'heading_permanent' => [
                    'class' => 'header c2',
                    'scope' => 'col'
                ],
                'heading_delete' => [
                    'class' => 'header c3 lastcol',
                    'scope' => 'col'
                ],
            ];

        } else if ($field == 'catalog') {
            $table_attributes = [
                'class' => 'generaltable',
                'id' => 'catalog_table'
            ];

            $th_attributes = [
                'heading_position' => [
                    'class' => 'header c0',
                    'scope' => 'col'
                ],
                'heading_name' => [
                    'class' => 'header c1',
                    'scope' => 'col'
                ],
                'heading_datatype' => [
                    'class' => 'header c2',
                    'scope' => 'col'
                ],
                'heading_required' => [
                    'class' => 'header c3',
                    'scope' => 'col'
                ],
                'heading_readonly' => [
                    'class' => 'header c4',
                    'scope' => 'col'
                ],
                'heading_params' => [
                    'class' => 'header c5',
                    'scope' => 'col'
                ],
                'heading_delete' => [
                    'class' => 'header c6 lastcol',
                    'scope' => 'col'
                ],
            ];
        }

        $output = html_writer::start_tag('table', $table_attributes);

        $output .= html_writer::start_tag('thead', array());
        $output .= html_writer::start_tag('tr', array());

        foreach ($th_attributes as $name =>$th) {
            $output .= html_writer::tag('th', get_string($name, 'block_opencast'), $th);
        }

        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('thead');

        return $output;
    }

    /**
     * Prints course categories and assigned moodle users.
     * @return string
     */
    private function table_body($field = 'role') {
        global $OUTPUT;
        $mform = $this->_form;

        $mform->addElement('html', '<tbody>');
        if ($field == 'role') {
            $roles = $this->getroles();
            foreach ($roles as $role) {
                $mform->addElement('html', '<tr>');

                $mform->addElement('html', '<td class="cell c0">');
                $name = 'role_' . $role->id;
                $mform->addElement('text', $name, null, array('size' => 50));
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, $role->rolename);

                $mform->addElement('html', '</td><td class="cell c1">');
                $name = 'action_' . $role->id;
                $mform->addElement('text', $name, null);
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, $role->actions);

                $mform->addElement('html', '</td><td class="cell c2">');
                $name = 'permanent_' . $role->id;
                $mform->addElement('advcheckbox', $name, null);
                $mform->setDefault($name, $role->permanent);

                $mform->addElement('html', '</td><td class="cell c3 lastcol">');
                $pix = $OUTPUT->action_icon(
                    new moodle_url('/blocks/opencast/adminsettings.php', array('d' => $role->id)),
                    new \pix_icon('t/delete', get_string('delete')),
                    null,
                    array('class' => 'action-delete')
                );
                $mform->addElement('html', $pix);
            }
        } else if ($field == 'catalog') {
            $catalogs = $this->getcatalogs();
            $position = 1;
            foreach ($catalogs as $catalog) {
                $mform->addElement('html', '<tr>');

                $mform->addElement('html', '<td class="cell c0">');
                $name = 'position_' . $catalog->id;
                $mform->addElement('static', $name, null, $position);

                $mform->addElement('html', '</td><td class="cell c1">');
                $name = 'catalog_name_' . $catalog->id;
                $mform->addElement('text', $name, null);
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, $catalog->name);

                $mform->addElement('html', '</td><td class="cell c2">');
                $name = 'catalog_datatype_' . $catalog->id;
                $datatypes = [
                    'text' => 'String (text)',
                    'select' => 'Drop Down (select)',
                    'autocomplete' => 'Arrays (autocomplete)',
                    'textarea' => 'Long Text (textarea)',
                    'date_time_selector' => 'Date Time Selector (datetime)'
                ];
                $mform->addElement('select', $name, null, $datatypes);
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, $catalog->datatype);
                $mform->disabledIf($name, 'catalog_readonly_' . $catalog->id, 'checked');

                $mform->addElement('html', '</td><td class="cell c3">');
                $name = 'catalog_required_' . $catalog->id;
                $mform->addElement('advcheckbox', $name, null);
                $mform->setDefault($name, $catalog->required);
                $mform->disabledIf($name, 'catalog_readonly_' . $catalog->id, 'checked');

                $mform->addElement('html', '</td><td class="cell c4">');
                $name = 'catalog_readonly_' . $catalog->id;
                $mform->addElement('advcheckbox', $name, null);
                $mform->setDefault($name, $catalog->readonly);

                $mform->addElement('html', '</td><td class="cell c5">');
                $name = 'catalog_params_' . $catalog->id;
                $mform->addElement('text', $name, null);
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, $catalog->param_json);

                $mform->addElement('html', '</td><td class="cell c6 lastcol">');
                $pix = $OUTPUT->action_icon(
                    new moodle_url('/blocks/opencast/adminsettings.php', array('cd' => $catalog->id)),
                    new \pix_icon('t/delete', get_string('delete')),
                    null,
                    array('class' => 'action-delete')
                );
                $mform->addElement('html', $pix);
                $position++;
            }
        }
        $mform->addElement('html', '</tbody>');
        $mform->addElement('html', '</table>');
    }

     /**
     * Returns metadata catalog.
     * @return array
     */
    private function getcatalogs() {
        global $DB;
        $catalogs = $DB->get_records('block_opencast_catalog', null, 'id');

        return $catalogs;
    }

    /**
     * Returns acl roles.
     * @return array
     */
    private function getroles() {
        global $DB;
        $roles = $DB->get_records('block_opencast_roles');

        return $roles;
    }

    /**
     * Validates, if all role and action fields are filled.
     *
     * @param array $data
     * @param array $files
     *
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $error = array();

        if (array_key_exists('addrolebutton', $data)) {
            foreach (['rolename', 'actions'] as $key) {
                if ($data[$key] === "") {
                    $error[$key] = get_string('required');
                }
            }
        } else if (array_key_exists('submitbutton', $data)) {
            // Validate that role and actions are not empty.
            foreach ($data as $key => $value) {
                if ((substr($key, 0, 5) === "role_" ||
                        substr($key, 0, 7) === "action_") &&
                    $value === ""
                ) {
                    $error[$key] = get_string('required');
                }
            }

            $apibridge = \block_opencast\local\apibridge::get_instance();

            // Validate upload workflow.
            if ($data['uploadworkflow'] !== "") {
                // Verify workflow.
                if (!$apibridge->check_if_workflow_exists($data['uploadworkflow'])) {
                    $error['uploadworkflow'] = get_string('workflow_not_existing', 'block_opencast');
                }
            }

            // Validate duplicate workflow.
            if ($data['duplicateworkflow'] !== "") {
                // Verify workflow.
                if (!$apibridge->check_if_workflow_exists($data['duplicateworkflow'])) {
                    $error['duplicateworkflow'] = get_string('workflow_not_existing', 'block_opencast');
                }
            }

            // Validate roles workflow.
            if ( $data['workflow_roles'] !== "" ) {
                // Verify workflow.
                if ( ! $apibridge->check_if_workflow_exists( $data['workflow_roles'] ) ) {
                    $error['workflow_roles'] = get_string( 'workflow_not_existing', 'block_opencast' );
                }
            }

            // Validate group name if a group should be created.
            if ( $data['group_creation'] === "1" ) {
                // Group name must not be empty.
                if ( empty($data['group_name']) ) {
                    $error['group_name'] = get_string( 'group_name_empty', 'block_opencast' );
                }
            }

            // Validate series name.
            if ( empty($data['series_name']) ) {
                // Series name must not be empty.
                $error['series_name'] = get_string( 'series_name_empty', 'block_opencast' );
            }
        }

        foreach ($this->getcatalogs() as $catalog) {
            $catalog_datatype = "catalog_datatype_{$catalog->id}";
            $catalog_readonly = "catalog_readonly_{$catalog->id}";
            $catalog_params = "catalog_params_{$catalog->id}";
            // Check for non empty params of static fields.
            if ($data[$catalog_readonly] == true &&
                empty($data[$catalog_params])) {
                $error[$catalog_params] = get_string( 'catalog_static_params_empty', 'block_opencast' );
            }
            // Check for empty or array/object parsable params of all other fields.
            if ($data[$catalog_readonly] == false &&
                    !empty($data[$catalog_params]) &&
                    !is_array(json_decode($data[$catalog_params])) &&
                    !is_object(json_decode($data[$catalog_params]))) {
                $error[$catalog_params] = get_string( 'catalog_params_noarray', 'block_opencast');
            }
        }

        return $error;
    }
}