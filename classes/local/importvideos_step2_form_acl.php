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
 * Manual import videos form for ACL Change import mode (Step 2: Summary).
 *
 * @package    block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni, ELAN e.V. <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Manual import videos form for ACL Change import mode (Step 2: Summary).
 *
 * @package    block_opencast
 * @copyright  2021 Farbod Zamani Boroujeni, ELAN e.V. <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importvideos_step2_form_acl extends \moodleform
{

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
        
        // Get series object first to know if we can proceed.
        $seriesobject = importvideosmanager::get_import_source_course_series_object($this->_customdata['sourcecourseid']);
        // If there isn't any series in the course.
        if (!$seriesobject) {
            // We are in a dead end situation, no chance to add anything.
            $notification = $renderer->wizard_error_notification(
                get_string('importvideos_wizardstep1seriesnotfound', 'block_opencast'));
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

        // Summary item: Series
        $seriesentry = $renderer->series_menu_entry($seriesobject);
        $mform->addElement('static', 'summaryseries',
            get_string('importvideos_wizardstep1series', 'block_opencast'),
            $seriesentry);

        // Horizontal line.
        $mform->addElement('html', '<hr>');

        // Summary item: Course videos.
        $coursevideossummary = importvideosmanager::get_import_acl_source_course_videos_summary(
            $this->_customdata['sourcecourseid']);
        $mform->addElement('static', 'summarycoursevideos',
            get_string('importvideos_wizardstep1coursevideos', 'block_opencast'),
            $coursevideossummary);

        // Add action buttons.
        $this->add_action_buttons(true, get_string('importvideos_wizardstepbuttontitlerunimport', 'block_opencast'));
    }
}
