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
 * Manual import videos form (Step 2: Select videos).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Manual import videos form (Step 2: Select videos).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importvideos_step2_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;

        // Define mform.
        $mform = $this->_form;

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Add hidden fields for transferring the wizard results and for wizard step processing.
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'step', 2);
        $mform->setType('step', PARAM_INT);
        $mform->addElement('hidden', 'sourcecourseid', $this->_customdata['sourcecourseid']);
        $mform->setType('sourcecourseid', PARAM_INT);
        $mform->addElement('hidden', 'ocinstanceid', $this->_customdata['ocinstanceid']);
        $mform->setType('ocinstanceid', PARAM_INT);

        // Get list of course series with videos.
        $courseseries = importvideosmanager::get_import_source_course_series_and_videos_menu($this->_customdata['ocinstanceid'],
            $this->_customdata['sourcecourseid']);

        // If there isn't any course video in the course.
        if (count($courseseries) < 1) {
            // We are in a dead end situation, no chance to add anything.
            $notification = $renderer->wizard_error_notification(
                get_string('importvideos_wizardstep2coursevideosnone', 'block_opencast'));
            $mform->addElement('html', $notification);
            $mform->addElement('cancel');

            return;
        }

        // Add intro.
        $notification = $renderer->wizard_intro_notification(
            get_string('importvideos_wizardstep2intro', 'block_opencast'));
        $mform->addElement('html', $notification);

        // Add one single empty static element.
        // This is just there to let us attach an validation error message as this can't be attached to the checkbox group.
        $mform->addElement('static', 'coursevideosvalidation', '', '');

        // Add multi-checkbox group to pick the course videos.
        foreach ($courseseries as $identifier => $info) {
            $mform->addElement('html', '<p>' . $info['title'] . '</p>');
            foreach ($info['videos'] as $videoid => $label) {
                $mform->addElement('advcheckbox', 'coursevideos[' . $videoid . ']', $label, null,
                    array('group' => 'coursevideocheckboxes'));
            }
        }
        $this->add_checkbox_controller('coursevideocheckboxes', null, null, 1);

        // Add action buttons.
        $this->add_action_buttons(true, get_string('importvideos_wizardstepbuttontitlecontinue', 'block_opencast'));
    }

    /**
     * Form validation.
     * @param array $data Form data
     * @param array $files Form files
     * @return array Validation results
     */
    public function validation($data, $files) {
        // Ask parent class for errors first.
        $errors = parent::validation($data, $files);

        // Analyze if any course video was selected.
        $anyvideoselected = false;
        foreach ($data['coursevideos'] as $checked) {
            if ($checked == 1) {
                $anyvideoselected = true;
                break;
            }
        }

        // If no video was selected, return an error.
        if ($anyvideoselected == false) {
            $errors['coursevideosvalidation'] = get_string('importvideos_wizardstep2coursevideosnoneselected', 'block_opencast');
        }

        // Return errors.
        return $errors;
    }
}
