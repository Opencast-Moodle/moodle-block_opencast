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
 * @package block_opencast
 * @copyright 2017 Tamara Gunkel
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use moodleform;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

class admin_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;

        // Section cron settings
        $mform->addElement('header', 'cron_header', get_string('cronsettings', 'block_opencast'));

        // Limit upload jobs
        $url = new moodle_url('/admin/tool/task/scheduledtasks.php');
        $link = html_writer::link($url, get_string('pluginname', 'tool_task'), array('target' => '_blank'));
        $name = 'limituploadjobs';
        $title = get_string('limituploadjobs', 'block_opencast');
        $description = get_string('limituploadjobsdesc', 'block_opencast', $link);
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 1);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Upload file limit.
        $name = 'uploadfilelimit';
        $title =   get_string('uploadfilelimit', 'block_opencast');
        $description =   get_string('uploadfilelimitdesc', 'block_opencast');
        // Unlimted, 20GB, 10GB, 5GB, 2GB, 1GB, 512MB, 256MB, 128MB, 64MB
        $sizelist = array(-1, 53687091200, 21474836480, 10737418240, 5368709120, 2147483648, 1073741824,
            536870912, 268435456, 134217728, 67108864);
        $filesizes = array();
        foreach ($sizelist as $sizebytes) {
            $filesizes[(string)intval($sizebytes)] = display_size($sizebytes);
        }
        $mform->addElement('select', $name, $title, $filesizes);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 2147483648);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Upload workflow
        $name = 'uploadworkflow';
        $title =   get_string('uploadworkflow', 'block_opencast');
        $description =   get_string('uploadworkflowdesc', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);
        $mform->setDefault($name, 'ng-schedule-and-upload');
        $mform->addElement('static', 'description' . $name, '', $description);

        // Publish to engage
        $name = 'publishtoengage';
        $title =    get_string('publishtoengage', 'block_opencast');
        $description =   get_string('publishtoengagedesc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 0);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Reuse existing upload
        $name = 'reuseexistingupload';
        $title =   get_string('reuseexistingupload', 'block_opencast');
        $description =    get_string('reuseexistinguploaddesc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 0);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Section overview settings
        $mform->addElement('header', 'overview_header', get_string('overviewsettings', 'block_opencast'));

        // Limit videos
        $name = 'limitvideos';
        $title =  get_string('limitvideos', 'block_opencast');
        $description =  get_string('limitvideos', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 5);
        $mform->addElement('static', 'description' . $name, '', $description);

        // Section access policies
        $mform->addElement('header', 'accesspolicies_header', get_string('accesspolicies', 'block_opencast'));

        // Group creation
        $name = 'group_creation';
        $title =   get_string('groupcreation', 'block_opencast');
        $description =  get_string('groupcreationdesc', 'block_opencast');
        $mform->addElement('advcheckbox', $name, $title);
        $mform->setDefault($name, 1);
        $mform->addElement('static', 'description' . $name, '', $description);


        // Group name
        $name = 'group_name';
        $title =   get_string('groupname', 'block_opencast');
        $description =  get_string('groupnamedesc', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);
        $mform->setDefault($name,'Moodle_course_[COURSEID]');
        $mform->addElement('static', 'description' . $name, '', $description);

        // Series name
        $name = 'series_name';
        $title =   get_string('seriesname', 'block_opencast');
        $description =  get_string('seriesnamedesc', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);
        $mform->setDefault($name, 'Course_Series_[COURSEID]');
        $mform->addElement('static', 'description' . $name, '', $description);

        // Acl roles
        $mform->addElement('header', 'roles_header', get_string('aclrolesname', 'block_opencast'));
        $mform->setExpanded('roles_header');

        // Workflow adding/deleting non-permanent roles.
        $name = 'workflow_roles';
        $title =   get_string('workflowrolesname', 'block_opencast');
        $description =  get_string('workflowrolesdesc', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);
        $mform->addElement('static', 'description' . $name, '', $description);

        // New role name.
        $name = 'rolename';
        $title =   get_string('rolename', 'block_opencast');
        $mform->addElement('text', $name, $title);
        $mform->setType($name, PARAM_TEXT);

        // New role action.
        $name = 'actions';
        $title =   get_string('actions', 'block_opencast');
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

        $mform->addElement('submit', 'submitbutton', get_string('submit', 'block_opencast'));

        $mform->addElement('html', '</div>');
    }

    /**
     * Prints the table head (e.g. column names).
     * @return string
     */
    public function tablehead() {
        $attributes['class'] = 'generaltable';
        $attributes['id'] = 'roles_table';
        $output = html_writer::start_tag('table', $attributes);

        $output .= html_writer::start_tag('thead', array());
        $output .= html_writer::start_tag('tr', array());

        $attributes = array();
        $attributes['class'] = 'header c0';
        $attributes['scope'] = 'col';
        $output .= html_writer::tag('th', get_string('heading_role', 'block_opencast'), $attributes);
        $attributes = array();
        $attributes['class'] = 'header c1';
        $attributes['scope'] = 'col';
        $output .= html_writer::tag('th', get_string('heading_actions', 'block_opencast'), $attributes);
        $attributes = array();
        $attributes['class'] = 'header c2';
        $attributes['scope'] = 'col';
        $output .= html_writer::tag('th', get_string('heading_permanent', 'block_opencast'), $attributes);
        $attributes = array();
        $attributes['class'] = 'header c3 lastcol';
        $attributes['scope'] = 'col';
        $output .= html_writer::tag('th', get_string('heading_delete', 'block_opencast'), $attributes);

        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('thead');

        return $output;
    }

    /**
     * Prints course categories and assigned moodle users.
     * @return string
     */
    private function table_body() {
        global $OUTPUT;
        $mform = $this->_form;

        $mform->addElement('html', '<tbody>');
        $roles = $this->getroles();
        foreach ($roles as $role) {
            $mform->addElement('html', '<tr>');

            $mform->addElement('html', '<td class="cell c0">');
            $name = 'role_'.$role->id;
            $mform->addElement('text', $name, null, array('size' => 50));
            $mform->setType($name, PARAM_TEXT);
            $mform->setDefault($name, $role->rolename);

            $mform->addElement('html', '</td><td class="cell c1">');
            $name = 'action_'.$role->id;
            $mform->addElement('text', $name, null);
            $mform->setType($name, PARAM_TEXT);
            $mform->setDefault($name, $role->actions);

            $mform->addElement('html', '</td><td class="cell c2">');
            $name = 'permanent_'.$role->id;
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
        $mform->addElement('html', '</tbody>');
        $mform->addElement('html', '</table>');
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
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    function validation($data, $files) {
        $error = array();
        if (array_key_exists('addrolebutton', $data)) {
            foreach (['rolename', 'actions'] as $key) {
                if ($data[$key] === "") {
                    $error[$key] = get_string('required');
                }
            }
        } else if (array_key_exists('submitbutton', $data)) {
            // Validate that role and actions are not empty
            foreach ($data as $key => $value) {
                if ((substr($key, 0, 5) === "role_" ||
                        substr($key, 0, 7) === "action_") &&
                    $value === "") {
                    $error[$key] = get_string('required');
                }
            }
        }
        return $error;
    }
}