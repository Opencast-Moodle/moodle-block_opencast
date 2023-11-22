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
 * Manual import videos form (Step 4: Summary).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Manual import videos form (Step 4: Summary).
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importvideos_step4_form extends moodleform {


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
        $mform->addElement('hidden', 'ocinstanceid', $this->_customdata['ocinstanceid']);
        $mform->setType('ocinstanceid', PARAM_INT);
        $mform->addElement('hidden', 'step', 4);
        $mform->setType('step', PARAM_INT);
        $mform->addElement('hidden', 'sourcecourseid', $this->_customdata['sourcecourseid']);
        $mform->setType('sourcecourseid', PARAM_INT);
        foreach ($this->_customdata['coursevideos'] as $identifier => $checked) {
            $mform->addElement('hidden', 'coursevideos[' . $identifier . ']', $checked);
            $mform->setType('coursevideos', PARAM_BOOL);
        }
        if (isset($this->_customdata['fixseriesmodules'])) {
            $mform->addElement('hidden', 'fixseriesmodules', $this->_customdata['fixseriesmodules']);
            $mform->setType('fixseriesmodules', PARAM_BOOL);
        }
        if (isset($this->_customdata['fixepisodemodules'])) {
            $mform->addElement('hidden', 'fixepisodemodules', $this->_customdata['fixepisodemodules']);
            $mform->setType('fixepisodemodules', PARAM_BOOL);
        }

        // If there wasn't any source course selected.
        // This should not happen in this step, but we never know.
        if (!is_number($this->_customdata['sourcecourseid'])) {
            // We are in a dead end situation, no chance to add anything.
            $notification = $renderer->wizard_error_notification(
                get_string('importvideos_wizardstep4sourcecoursenone', 'block_opencast'));
            $mform->addElement('html', $notification);
            $mform->addElement('cancel');
            return;
        }

        // If there wasn't any course video selected.
        // This should not happen in this step, but we never know.
        if (count($this->_customdata['coursevideos']) < 1) {
            // We are in a dead end situation, no chance to add anything.
            $notification = $renderer->wizard_error_notification(
                get_string('importvideos_wizardstep4coursevideosnone', 'block_opencast'));
            $mform->addElement('html', $notification);
            $mform->addElement('cancel');
            return;
        }

        // Add intro.
        $notification = $renderer->wizard_intro_notification(
            get_string('importvideos_wizardstep4intro', 'block_opencast'));
        $mform->addElement('html', $notification);

        // Summary item: Source course.
        $sourcecourse = get_course($this->_customdata['sourcecourseid']);
        $courseentry = $renderer->course_menu_entry($sourcecourse);
        $mform->addElement('static', 'summarysourcecourse',
            get_string('importvideos_wizardstep1sourcecourse', 'block_opencast'),
            $courseentry);

        // Horizontal line.
        $mform->addElement('html', '<hr>');

        // Summary item: Course videos.
        $courseseriessummary = importvideosmanager::get_import_source_course_videos_summary(
            $this->_customdata['ocinstanceid'],
            $this->_customdata['sourcecourseid'], $this->_customdata['coursevideos']);
        $mform->addElement('static', 'summaryimportvideotitle',
            get_string('importvideos_wizardstep2coursevideos', 'block_opencast'), '');
        foreach ($courseseriessummary as $series => $info) {
            $importvideocounter = 1;
            foreach ($info['videos'] as $identifier => $label) {
                $mform->addElement('static', 'summaryimportvideo' . $importvideocounter,
                    ($importvideocounter == 1) ? $info['title'] : '',
                    $label);
                $importvideocounter++;
            }
        }

        // Summary item: Handle modules.
        if ((isset($this->_customdata['fixseriesmodules']) && $this->_customdata['fixseriesmodules'] == true) ||
            (isset($this->_customdata['fixepisodemodules']) && $this->_customdata['fixepisodemodules'] == true)) {
            // Horizontal line.
            $mform->addElement('html', '<hr>');

            // Handle series modules.
            if (isset($this->_customdata['fixseriesmodules']) && $this->_customdata['fixseriesmodules'] == true) {
                // Show summary item.
                $mform->addElement('static', 'summaryfixseriesmodules',
                    get_string('importvideos_wizardstep3heading', 'block_opencast'),
                    get_string('importvideos_wizardstep3seriesmodulesubheading', 'block_opencast'));

                // Remember this fact for the next summary item.
                $summaryfixseriesmodulesshown = true;
            } else {
                // Remember the fact for the next summary item.
                $summaryfixseriesmodulesshown = false;
            }

            // Handle episode modules.
            if (isset($this->_customdata['fixepisodemodules']) && $this->_customdata['fixepisodemodules'] == true) {
                $mform->addElement('static', 'summaryfixepisodemodules',
                    ($summaryfixseriesmodulesshown == false) ? get_string('importvideos_wizardstep3heading', 'block_opencast') : '',
                    get_string('importvideos_wizardstep3episodemodulesubheading', 'block_opencast'));
            }
        }

        // Add action buttons.
        $this->add_action_buttons(true, get_string('importvideos_wizardstepbuttontitlerunimport', 'block_opencast'));
    }
}
