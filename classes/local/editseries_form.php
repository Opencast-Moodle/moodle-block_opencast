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

use tool_opencast\seriesmapping;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Course series form.
 *
 * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @author     Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editseries_form extends \moodleform
{

    /**
     * Form definition.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;
        $ocinstanceid = $this->_customdata['ocinstanceid'];

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $series = $DB->get_records('tool_opencast_series', array('ocinstanceid' => $ocinstanceid, 'courseid' => $this->_customdata['courseid']));
        // Transform isdefault to int.
        array_walk($series, function ($item) {
            $item->isdefault = intval($item->isdefault);
        });

        $mform->addElement('hidden', 'seriesinput', json_encode(array_values($series)));
        $mform->setType('seriesinput', PARAM_TEXT);

        $mform->addElement('hidden', 'ocinstanceid', $ocinstanceid);
        $mform->setType('ocinstanceid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validates, if all role and action fields are filled.
     *
     * @param array $data
     * @param array $files
     *
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $error = array();

        $apibridge = apibridge::get_instance($data['ocinstanceid']);

        if (!empty($data['seriesid'])) {
            $seriesid = $apibridge->get_series_by_identifier($data['seriesid']);
            if (!$seriesid) {
                $error['seriesid'] = get_string('series_does_not_exist_admin', 'block_opencast', $data['seriesid']);
            }
        }

        return $error;
    }
}
