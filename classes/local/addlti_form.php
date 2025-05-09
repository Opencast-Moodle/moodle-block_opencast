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
 * Add LTI series module form.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use core_availability\frontend;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Add LTI series module form.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addlti_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $courseid = $this->_customdata['courseid'];
        $ocinstanceid = $this->_customdata['ocinstanceid'];
        $seriesid = $this->_customdata['seriesid'];

        $mform->addElement('text', 'title', get_string('addlti_formltititle', 'block_opencast'), ['size' => '40']);
        $mform->setType('title', PARAM_TEXT);
        $mform->setDefault('title', ltimodulemanager::get_default_title_for_series($ocinstanceid));
        $mform->addRule('title',
            get_string('addlti_noemptytitle', 'block_opencast', get_string('addlti_defaulttitle', 'block_opencast')),
            'required');

        if (get_config('tool_opencast', 'addltiintro_' . $ocinstanceid) == true) {
            $mform->addElement('editor', 'intro', get_string('addlti_formltiintro', 'block_opencast'),
                ['rows' => 5],
                ['maxfiles' => 0, 'noclean' => true]);
            $mform->setType('intro', PARAM_RAW); // No XSS prevention here, users must be trusted.
        }

        if (get_config('tool_opencast', 'addltisection_' . $ocinstanceid) == true) {
            // Get course sections.
            $sectionmenu = ltimodulemanager::get_course_sections($courseid);

            // Add the widget only if we have more than one section.
            if (count($sectionmenu) > 1) {
                $mform->addElement('select', 'section', get_string('addlti_formltisection', 'block_opencast'),
                    ltimodulemanager::get_course_sections($courseid));
                $mform->setType('section', PARAM_INT);
                $mform->setDefault('section', 0);
            }
        }

        if (get_config('tool_opencast', 'addltiavailability_' . $ocinstanceid) == true && !empty($CFG->enableavailability)) {
            $mform->addElement('textarea', 'availabilityconditionsjson',
                get_string('addlti_formltiavailability', 'block_opencast'));
            frontend::include_all_javascript(get_course($courseid));
        }

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'ocinstanceid', $ocinstanceid);
        $mform->setType('ocinstanceid', PARAM_INT);

        $mform->addElement('hidden', 'seriesid', $seriesid);
        $mform->setType('seriesid', PARAM_ALPHANUMEXT);

        $this->add_action_buttons();
    }

    /**
     * Overriding formslib's add_action_buttons() method, to add an extra submit button.
     *
     * @param bool $cancel Not used
     * @param string $submitlabel Not used
     *
     * @return void
     */
    public function add_action_buttons($cancel = true, $submitlabel = null) {
        $mform = $this->_form;

        // Elements in a row need a group.
        $buttonarray = [];

        // Submit buttons.
        $submitlabel = get_string('addlti_addbuttontitlereturnoverview', 'block_opencast');
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
        $submit2label = get_string('addlti_addbuttontitlereturncourse', 'block_opencast');
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton2', $submit2label);

        // Cancel button.
        $buttonarray[] = &$mform->createElement('cancel');

        // Show group.
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->setType('buttonar', PARAM_RAW);
        $mform->closeHeaderBefore('buttonar');
    }
}
