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
 * Course series form.
 *
 * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @author     Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class editseries_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $attributes = array(
            'size' => '40',
            'placeholder' => get_string('noseriesid', 'block_opencast'));

        $mform->addElement('text', 'seriesid', get_string('form_seriesid', 'block_opencast'),
            $attributes);
        $mform->setType('seriesid', PARAM_TEXT);

        $apibridge = apibridge::get_instance();
        $seriesid = $apibridge->get_course_series($this->_customdata['courseid']);

        if ($seriesid) {
            $mform->setDefault('seriesid',  $seriesid->identifier);
        }

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
