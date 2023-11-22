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
 * Add new transcription form.
 *
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use html_writer;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Add new transcription form.
 *
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addtranscription_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;
        // Get the renderer to use its methods.
        $this->renderer = $PAGE->get_renderer('block_opencast');
        $ocinstanceid = $this->_customdata['ocinstanceid'];
        $identifier = $this->_customdata['identifier'];
        $courseid = $this->_customdata['courseid'];

        $mform = $this->_form;

        $explanation = html_writer::tag('p', get_string('addnewtranscription_desc', 'block_opencast'));
        $mform->addElement('html', $explanation);

        $transcriptiontypescfg = get_config('block_opencast', 'transcriptionfileextensions_' . $ocinstanceid);
        if (empty($transcriptiontypescfg)) {
            // Fallback. Use Moodle defined html_track file types.
            $transcriptiontypes = ['html_track'];
        } else {
            $transcriptiontypes = [];
            foreach (explode(',', $transcriptiontypescfg) as $transcriptiontype) {
                if (empty($transcriptiontype)) {
                    continue;
                }
                $transcriptiontypes[] = $transcriptiontype;
            }
        }

        // Preparing flavors as for service types.
        $flavorsconfig = get_config('block_opencast', 'transcriptionflavors_' . $ocinstanceid);
        $flavors = [
            '' => get_string('emptyflavoroption', 'block_opencast'),
        ];
        if (!empty($flavorsconfig)) {
            $flavorsarray = json_decode($flavorsconfig);
            foreach ($flavorsarray as $flavor) {
                if (!empty($flavor->key) && !empty($flavor->value)) {
                    $flavors[$flavor->key] = format_string($flavor->value);
                }
            }
        }

        $mform->addElement('select', 'transcription_flavor', get_string('transcriptionflavorfield', 'block_opencast'), $flavors);
        $mform->addRule('transcription_flavor', get_string('required'), 'required');
        $mform->addElement('filepicker', 'transcription_file', get_string('transcriptionfilefield', 'block_opencast'),
            null, ['accepted_types' => $transcriptiontypes]);
        $mform->disabledIf('transcription_file', 'transcription_flavor', 'eq', '');
        $mform->addRule('transcription_file', get_string('required'), 'required');

        $mform->addElement('hidden', 'ocinstanceid', $this->_customdata['ocinstanceid']);
        $mform->setType('ocinstanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'video_identifier', $this->_customdata['identifier']);
        $mform->setType('video_identifier', PARAM_INT);

        $mform->closeHeaderBefore('buttonar');

        $this->add_action_buttons(true, get_string('uploadtranscritpion', 'block_opencast'));
    }
}
