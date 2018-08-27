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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

class createseries_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $apibridge = \block_opencast\local\apibridge::get_instance();

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'seriestitle', get_string('form_seriestitle', 'block_opencast', array('size' => '40')));
        $mform->setType('seriestitle', PARAM_TEXT);
        $mform->addRule('seriestitle', get_string('required'), 'required', null, 'server');
        $mform->setDefault('seriestitle', $apibridge->get_default_seriestitle($this->_customdata['courseid']));

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
