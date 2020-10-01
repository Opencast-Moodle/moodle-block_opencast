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
 * Manual import videos form (Step 1: Select source course).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Manual import videos form (Step 1: Select source course).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importvideos_step1_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        // Define mform.
        $mform = $this->_form;

        // Get list of source courses.
        $sourcecourses = importvideosmanager::get_import_source_courses_menu($this->_customdata['courseid']);

        // If there isn't any source course.
        if (count($sourcecourses) < 1) {
            // We are in a dead end situation, no chance to add anything.
            $notification = importvideosmanager::render_wizard_error_notification(
                    get_string('importvideos_wizardstep1sourcecoursenone', 'block_opencast'));
            $mform->addElement('html', $notification);
            $mform->addElement('cancel');
            return;
        }

        // Add hidden fields for transferring the wizard results and for wizard step processing.
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'step', 1);
        $mform->setType('step', PARAM_INT);

        // Add intro.
        $notification = importvideosmanager::render_wizard_intro_notification(
                get_string('importvideos_wizardstep1intro', 'block_opencast'));
        $mform->addElement('html', $notification);

        // Add widget to pick the source course.
        $mform->addElement('select', 'sourcecourseid', '', $sourcecourses);

        // Add action buttons.
        $this->add_action_buttons(true, get_string('importvideos_wizardstepbuttontitlecontinue', 'block_opencast'));
    }
}
