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
 * Upload video form.
 *
 * @package    block_opencast
 * @copyright  2019 Farbod Zamani, ELAN e.V.
 * @author     Farbod Zamani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

class updatemetadata_form extends \moodleform {

    public function definition() {

        $mform = $this->_form;

        $metadata = $this->_customdata['metadata'];
        
        unset($metadata[array_search('isPartOf', array_column($metadata,'id'))]);
        $metadata = array_values($metadata);

        $date = $metadata[array_search('date', array_column($metadata,'type'))];
        $time = $metadata[array_search('time', array_column($metadata,'type'))];
        $date_time = strtotime($date->value . ' '  . $time->value);

        unset($metadata[array_search('startTime', array_column($metadata,'id'))]);
        $metadata = array_values($metadata);


        $languages = $this->_customdata['languages'] ? json_decode($this->_customdata['languages']->param_json, true) : [
            "" => "No option selected",
            "slv" => "Slovenian",
            "por" => "Portugese",
            "roh" => "Romansh",
            "ara" => "Arabic",
            "pol" => "Polish",
            "ita" => "Italian",
            "zho" => "Chinese",
            "fin" => "Finnish",
            "dan" => "Danish",
            "ukr" => "Ukrainian",
            "fra" => "French",
            "spa" => "Spanish",
            "gsw" => "Swiss German",
            "nor" => "Norwegian",
            "rus" => "Russian",
            "jpx" => "Japanese",
            "nld" => "Dutch",
            "tur" => "Turkish",
            "hin" => "Hindi",
            "swa" => "Swedish",
            "eng" => "English",
            "deu" => "German"
        ];

        $licenses = $this->_customdata['licenses'] ? json_decode($this->_customdata['licenses']->param_json, true) : [
            "" => "No option selected",
            "ALLRIGHTS" => "All Rights Reserved",
            "CC0" => "CC0",
            "CC-BY-ND" => "CC BY-ND",
            "CC-BY-NC-ND" => "CC BY-NC-ND",
            "CC-BY-NC-SA" => "CC BY-NC-SA",
            "CC-BY-SA" => "CC BY-SA",
            "CC-BY-NC" => "CC BY-NC",
            "CC-BY" => "CC BY"
        ];

        foreach ($metadata as $field) {
            $type = 'text';
            $param = array();
            $attributes = array();

            if ($field->id == 'startDate') {
                $type = 'date_time_selector';
                if ($date_time) {
                    $field->value = $date_time;
                }
            }
            if ($field->id == 'description') {
                $type = 'textarea';
            }
            if ($field->id == 'language') {
                $type = 'select';
                $param = $languages;
            }
            if ($field->id == 'license') {
                $type = 'select';
                $param = $licenses;
            }
            if (is_array($field->value)) {
                $type = 'autocomplete';
                $attributes = [
                    'multiple' => true,
                    'placeholder' => 'Enter ' . get_string($field->id, 'block_opencast'),
                    'showsuggestions' => false,
                    'noselectionstring' => 'No ' . get_string($field->id, 'block_opencast') . ' selected!' ,  
                    'tags' => true
                ];
                foreach ($field->value as $val) {
                    $param[$val] = $val;
                }
            }
            if ($field->readOnly) {
                $type = 'static';
                $param = $field->type == "date" ? date('Y-m-d H:i', strtotime($field->value)) : $field->value;
            }
            $mform->addElement($type, $field->id, get_string($field->id, 'block_opencast'), $param, $attributes);
            if ($field->required) {
                $mform->addRule($field->id, get_string('required'), 'required');
            }
            if ($field->value AND !$field->readOnly) {
                $mform->setDefault($field->id, $field->value);
            }
            if ($type == 'text') {
                $mform->setType($field->id, PARAM_TEXT);
            }
        }

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'video_identifier', $this->_customdata['identifier']);
        $mform->setType('courseid', PARAM_ALPHANUMEXT);

        $mform->closeHeaderBefore('buttonar');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

}
