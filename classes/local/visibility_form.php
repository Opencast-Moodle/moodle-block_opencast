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
 * Create series form.
 *
 * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @author     Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use block_opencast\groupaccess;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

class visibility_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $apibridge = \block_opencast\local\apibridge::get_instance();

        $courseid = $this->_customdata['courseid'];
        $eventid = $this->_customdata['identifier'];
        $visibility = $this->_customdata['visibility'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'identifier', $eventid);
        $mform->setType('identifier', PARAM_INT);

        // Check if there are roles, which allow group visibility.
        $roles = $apibridge->getroles(array("permanent" => 0));
        $groupvisibilityallowed = false;
        foreach ($roles as $role) {
            if (strpos($role->rolename, '[COURSEGROUPID]') >= 0) {
                $groupvisibilityallowed = true;
                break;
            }
        }

        $groups = groups_get_all_groups($courseid);

        $radioarray=array();
        $radioarray[] = $mform->addElement('radio', 'visibility',
            get_string('visibility', 'block_opencast'), get_string('visibility_hide', 'block_opencast'), 0);
        $radioarray[] = $mform->addElement('radio', 'visibility', '', get_string('visibility_show', 'block_opencast'), 1);
        if ($groupvisibilityallowed) {
            $attributes = array();
            if (empty($groups)) {
                $attributes = array('disabled' => true);
            }
            $radioarray[] = $mform->addElement('radio', 'visibility', '', get_string('visibility_group', 'block_opencast'), 2,
                $attributes);
        }
        $mform->setDefault('visibility', $visibility);
        $mform->setType('visibility', PARAM_INT);

        // Load existing groups.
        if ($groupvisibilityallowed) {

            $options = [];

            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }

            $select = $mform->addElement('select', 'groups', get_string('groups'), $options);
            $select->setMultiple(true);

            $selectedgroups = groupaccess::get_record(array('opencasteventid' => $eventid));
            if ($selectedgroups && $groups = $selectedgroups->get('groups')) {
                $groupsarray = explode(',', $groups);
                $select->setSelected($groupsarray);
            }
            $mform->hideIf('groups', 'visibility', 'neq', 2);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
