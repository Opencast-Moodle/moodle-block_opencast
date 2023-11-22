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
 * Edit Scheduled Visibility form.
 *
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use block_opencast_renderer;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Edit Scheduled Visibility form.
 *
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduledvisibility_form extends moodleform
{

    /**
     * Form definition.
     */
    public function definition()
    {
        global $PAGE;
        $mform = $this->_form;

        // Get the renderer to use its methods.
        $renderer = $PAGE->get_renderer('block_opencast');
        $courseid = $this->_customdata['courseid'];
        $uploadjobid = $this->_customdata['uploadjobid'];
        $ocinstanceid = $this->_customdata['ocinstanceid'];
        $scheduledvisibility = $this->_customdata['scheduledvisibility'];

        $apibridge = apibridge::get_instance($ocinstanceid);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'uploadjobid', $uploadjobid);
        $mform->setType('uploadjobid', PARAM_INT);

        $mform->addElement('hidden', 'ocinstanceid', $ocinstanceid);
        $mform->setType('ocinstanceid', PARAM_INT);

        $initialvisibilitystatus = intval($scheduledvisibility->initialvisibilitystatus);
        $mform->addElement('hidden', 'initialvisibilitystatus', $initialvisibilitystatus);
        $mform->setType('initialvisibilitystatus', PARAM_INT);

        $mform->addElement('hidden', 'initialvisibilitygroups', $scheduledvisibility->initialvisibilitygroups);
        $mform->setType('initialvisibilitygroups', PARAM_TEXT);

        $stringid = '';
        switch ($initialvisibilitystatus) {
            case block_opencast_renderer::GROUP:
                $stringid = 'visibility_group';
                break;
            case block_opencast_renderer::VISIBLE:
                $stringid = 'visibility_show';
                break;
            default:
                $stringid = 'visibility_hide';
                break;
        }
        $initialvisibilitystatustxt = get_string($stringid, 'block_opencast');
        $mform->addElement('static', 'initialvisibilitystatustxt',
            get_string('initialvisibilitystatus', 'block_opencast'), $initialvisibilitystatustxt);

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

        // Scheduled visibility.
        list($waitingtime, $configuredtimespan) = visibility_helper::get_waiting_time($ocinstanceid);
        $element = $mform->addElement('date_time_selector', 'scheduledvisibilitytime',
            get_string('scheduledvisibilitytime', 'block_opencast'));
        $element->_helpbutton = $renderer->render_help_icon_with_custom_text(
            get_string('scheduledvisibilitytimehi', 'block_opencast'),
            get_string('scheduledvisibilitytimehi_help', 'block_opencast', $configuredtimespan));

        // Setting default scheduled visibility time if this event has already been scheduled.
        if (!empty($scheduledvisibility) && !empty($scheduledvisibility->scheduledvisibilitytime)) {
            $waitingtime = intval($scheduledvisibility->scheduledvisibilitytime);
        }
        $mform->setDefault('scheduledvisibilitytime', $waitingtime);

        $scheduleradioarray = [];
        $scheduleradioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
            get_string('scheduledvisibilitystatus', 'block_opencast'), get_string('visibility_hide', 'block_opencast'), 0);
        $scheduleradioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus', '',
            get_string('visibility_show', 'block_opencast'), 1);
        // We need to remove the group visibility radio button, we there is no group in the course.
        if ($groupvisibilityallowed && !empty($groups)) {
            $scheduleradioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
                '', get_string('visibility_group', 'block_opencast'), 2);
        }

        // Setting default scheduled visibility status if this event has already been scheduled.
        $defaultstatus = block_opencast_renderer::HIDDEN;
        if (!empty($scheduledvisibility) && property_exists($scheduledvisibility, 'scheduledvisibilitystatus')) {
            $defaultstatus = intval($scheduledvisibility->scheduledvisibilitystatus);
        }
        $mform->setDefault('scheduledvisibilitystatus', $defaultstatus);
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
            // Set already selected groups for scheduled visibility, if exists.
            if (!empty($scheduledvisibility) && !empty($scheduledvisibility->scheduledvisibilitygroups)) {
                $scheduledgroups = json_decode($scheduledvisibility->scheduledvisibilitygroups, true);
                $select->setSelected($scheduledgroups);
            }
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
    public function validation($data, $files)
    {
        $errors = parent::validation($data, $files);
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
            $errors['scheduledvisibilitystatus'] = get_string('emptyvisibilitygroups', 'block_opencast');
        }
        // Check whether the scheduled visibility is equal to initial visibility.
        if (intval($data['scheduledvisibilitystatus']) == intval($data['initialvisibilitystatus'])) {
            $haserror = true;
            if ($data['scheduledvisibilitystatus'] == block_opencast_renderer::GROUP) {
                sort($data['scheduledvisibilitygroups']);
                $initialvisibilitygroups = [];
                if (isset($data['initialvisibilitygroups'])) {
                    $initialvisibilitygroups = json_decode($data['initialvisibilitygroups'], true);
                }
                sort($initialvisibilitygroups);
                if ($data['scheduledvisibilitygroups'] != $initialvisibilitygroups) {
                    $haserror = false;
                }
            }
            if ($haserror) {
                $errors['initialvisibilitystatustxt'] = get_string('scheduledvisibilitystatuserror', 'block_opencast');
            }
        }

        return $errors;
    }
}
