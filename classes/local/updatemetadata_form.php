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

        foreach ($this->_customdata['metadata_catalog'] as $field) {
            $value = $this->extract_value($field->name);
            $param = array();
            $attributes = array();
            if ($field->param_json) {
                $field->param_json = format_string($field->param_json, true, array('filter' => 'true'));
                $param = $field->datatype == 'static' ? $field->param_json : (array)json_decode($field->param_json);
            }
            if ($field->datatype == 'autocomplete') {
                $attributes = [
                    'multiple' => true,
                    'placeholder' => get_string('metadata_autocomplete_placeholder', 'block_opencast',
                    $this->try_get_string($field->name, 'block_opencast')),
                    'showsuggestions' => true, // if true, admin is able to add suggestion via admin page. Otherwise no suggestions!
                    'noselectionstring' => get_string('metadata_autocomplete_noselectionstring', 'block_opencast',
                    $this->try_get_string($field->name, 'block_opencast')),
                    'tags' => true
                ];
                foreach ($value as $val) {
                    $param[$val] = $val;
                }
            }
            
            $mform->addElement($field->datatype, $field->name, $this->try_get_string($field->name, 'block_opencast'), $param, $attributes);
            
            if ($field->datatype == 'text') {
                $mform->setType($field->name, PARAM_TEXT);
            }
            
            if ($field->required) {
                $mform->addRule($field->name, get_string('required'), 'required');
            }

            if ($value) {
                $mform->setDefault($field->name, $value);
            }
                  
        }

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'video_identifier', $this->_customdata['identifier']);
        $mform->setType('video_identifier', PARAM_ALPHANUMEXT);

        $mform->closeHeaderBefore('buttonar');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
    /**
     * Tries to get the string for identifier and component.
     * As a fallback it outputs the identifier itself with the first letter being uppercase.
     * @param string $identifier The key identifier for the localized string
     * @param string $component The module where the key identifier is stored,
     *      usually expressed as the filename in the language pack without the
     *      .php on the end but can also be written as mod/forum or grade/export/xls.
     *      If none is specified then moodle.php is used.
     * @param string|object|array $a An object, string or number that can be used
     *      within translation strings
     * @return string
     * @throws \coding_exception
     */
    protected function try_get_string($identifier, $component = '', $a = null) {
        if (!get_string_manager()->string_exists($identifier, $component)) {
            return ucfirst($identifier);
        } else {
            return get_string($identifier, $component, $a);
        }
    }

    /**
     * Searches through metadata to find the value of the field defined in catalog
     * @param string $fieldname the name of the catalog field which is defined as id in metadata set
     * @return string|array $value An array or string derived from metadata
     */
    protected function extract_value($fieldname) {
        $metadata = $this->_customdata['metadata'];

        foreach ($metadata as $data) {

            if ($data->id == $fieldname) {
                return $data->value;
            }
        }
        return '';
    }

}
