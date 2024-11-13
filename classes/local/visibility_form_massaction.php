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
 * Change Visibility Mass Action Form
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use block_opencast\groupaccess;
use block_opencast_renderer;
use moodleform;
use html_writer;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Change Visibility Mass Action Form
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class visibility_form_massaction extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;
        $mform = $this->_form;

        // Get the renderer to use its methods.
        $renderer = $PAGE->get_renderer('block_opencast');
        $courseid = $this->_customdata['courseid'];
        $videosdatalist = $this->_customdata['videosdatalist'];
        $ocinstanceid = $this->_customdata['ocinstanceid'];

        $apibridge = apibridge::get_instance($ocinstanceid);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'ocinstanceid', $ocinstanceid);
        $mform->setType('ocinstanceid', PARAM_INT);

        $mform->addElement('hidden', 'ismassaction', 1);
        $mform->setType('ismassaction', PARAM_INT);

        $videoslisthtmlitem = [];
        foreach ($videosdatalist as $videodata) {
            $videoslisthtmlitem[] = $videodata->detail;
            if (empty($videodata->error)) {
                $mform->addElement('hidden', 'videoids[]', $videodata->identifier);
            }
        }
        $mform->setType('videoids', PARAM_ALPHANUMEXT);
        if (!empty($videoslisthtmlitem)) {
            $line = html_writer::tag('hr', '');
            $explanation = html_writer::tag('p',
                get_string('massaction_selectedvideos_list', 'block_opencast',
                    implode('</li><li>', $videoslisthtmlitem))
            );
            $mform->addElement('html', $line . $explanation . $line);
        }

        // Check if the teacher should be allowed to restrict the episode to course groups.
        $groups = [];
        $groupvisibilityallowed = false;
        $controlgroupsenabled = get_config('block_opencast', 'aclcontrolgroup_' . $ocinstanceid);

        // If group restriction is generally enabled, check if there are roles which allow group visibility.
        if ($controlgroupsenabled) {
            $roles = $apibridge->getroles(0);
            foreach ($roles as $role) {
                if (strpos($role->rolename, '[COURSEGROUPID]') >= 0) {
                    $groupvisibilityallowed = true;
                    $groups = groups_get_all_groups($courseid);
                    break;
                }
            }
        }

        $radioarray = [];
        $radioarray[] = $mform->addElement('radio', 'visibility',
            get_string('visibility_massaction', 'block_opencast'), get_string('visibility_hide_massaction', 'block_opencast'), 0);
        $radioarray[] = $mform->addElement('radio', 'visibility', '',
            get_string('visibility_show_massaction', 'block_opencast'), 1);
        // We need to remove the group visibility radio button, when there is no group in the course.
        if ($groupvisibilityallowed && !empty($groups)) {
            $radioarray[] = $mform->addElement('radio', 'visibility', '',
                get_string('visibility_group_massaction', 'block_opencast'), 2);
        }
        $mform->setDefault('visibility', block_opencast_renderer::VISIBLE);
        $mform->setType('visibility', PARAM_INT);

        // Load existing groups.
        if ($groupvisibilityallowed && !empty($groups)) {

            $options = [];

            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }

            $select = $mform->addElement('select', 'groups', get_string('groups'), $options);
            $select->setMultiple(true);

            $mform->hideIf('groups', 'visibility', 'neq', 2);
        }

        // Provide a checkbox to enable changing the visibility for later.
        $mform->addElement('checkbox', 'enableschedulingchangevisibility',
            get_string('enableschedulingchangevisibility_massaction', 'block_opencast'),
            get_string('enableschedulingchangevisibilitydesc_massaction', 'block_opencast'));
        $mform->hideIf('scheduledvisibilitytime', 'enableschedulingchangevisibility', 'notchecked');
        $mform->hideIf('scheduledvisibilitystatus', 'enableschedulingchangevisibility', 'notchecked');

        $mform->setDefault('enableschedulingchangevisibility', false);

        // Scheduled visibility.
        list($waitingtime, $configuredtimespan) = visibility_helper::get_waiting_time($ocinstanceid);
        $element = $mform->addElement('date_time_selector', 'scheduledvisibilitytime',
            get_string('scheduledvisibilitytime', 'block_opencast'));
        $element->_helpbutton = $renderer->render_help_icon_with_custom_text(
            get_string('scheduledvisibilitytimehi', 'block_opencast'),
            get_string('scheduledvisibilitytimehi_help', 'block_opencast', $configuredtimespan));

        $mform->setDefault('scheduledvisibilitytime', $waitingtime);

        $scheduleradioarray = [];
        $scheduleradioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
            get_string('scheduledvisibilitystatus', 'block_opencast'),
            get_string('visibility_hide_massaction', 'block_opencast'),
            0
        );
        $scheduleradioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus', '',
            get_string('visibility_show_massaction', 'block_opencast'), 1);
        // We need to remove the group visibility radio button, we there is no group in the course.
        if ($groupvisibilityallowed && !empty($groups)) {
            $scheduleradioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
                '', get_string('visibility_group_massaction', 'block_opencast'), 2);
        }

        $mform->setDefault('scheduledvisibilitystatus', block_opencast_renderer::HIDDEN);
        $mform->setType('scheduledvisibilitystatus', PARAM_INT);

        // Load existing groups.
        if ($groupvisibilityallowed && !empty($groups)) {
            $options = [];
            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }
            $select = $mform->addElement('select', 'scheduledvisibilitygroups', get_string('groups'), $options);
            $select->setMultiple(true);
            $mform->hideIf('scheduledvisibilitygroups', 'scheduledvisibilitystatus', 'neq', 2);
            $mform->hideIf('scheduledvisibilitygroups', 'enableschedulingchangevisibility', 'notchecked');
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['visibility'] == block_opencast_renderer::GROUP && empty($data['groups'])) {
            $errors['visibility'] = get_string('emptyvisibilitygroups', 'block_opencast');
        }
        if (isset($data['enableschedulingchangevisibility']) && $data['enableschedulingchangevisibility']) {
            // Deducting 2 minutes from the time, to let teachers finish the form.
            $customminutes = [
                'minutes' => 2,
                'action' => 'minus',
            ];
            // Get custom allowed scheduled visibility time.
            $waitingtimearray = visibility_helper::get_waiting_time(
                $this->_customdata['ocinstanceid'], $customminutes);
            $allowedscheduledvisibilitytime = $waitingtimearray[0];
            if (intval($data['scheduledvisibilitytime']) < intval($allowedscheduledvisibilitytime)) {
                $errors['scheduledvisibilitytime'] = get_string('scheduledvisibilitytimeerror',
                    'block_opencast', $waitingtimearray[1]);
            }
            if ($data['scheduledvisibilitystatus'] == block_opencast_renderer::GROUP &&
                empty($data['scheduledvisibilitygroups'])) {
                $errors['enableschedulingchangevisibility'] = get_string('emptyvisibilitygroups', 'block_opencast');
            }
            // Check whether the scheduled visibility is equal to initial visibility.
            if (intval($data['scheduledvisibilitystatus']) == intval($data['visibility'])) {
                $haserror = true;
                if ($data['scheduledvisibilitystatus'] == block_opencast_renderer::GROUP) {
                    sort($data['scheduledvisibilitygroups']);
                    sort($data['groups']);
                    if ($data['scheduledvisibilitygroups'] != $data['groups']) {
                        $haserror = false;
                    }
                }
                if ($haserror) {
                    $errors['enableschedulingchangevisibility'] = get_string('scheduledvisibilitystatuserror', 'block_opencast');
                }
            }
        }

        return $errors;
    }
}
