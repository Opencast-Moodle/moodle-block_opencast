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
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use core_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

class addvideo_form extends \moodleform {

    public function definition() {

        $mform = $this->_form;

        $mform->addElement('header', 'metadata', get_string('metadata', 'block_opencast'));

            $set_title = true;
            foreach ($this->_customdata['metadata_catalog'] as $field) {
                $param = array();
                $attributes = array();
                if ($field->name == 'title') {
                    if ($field->required) {
                        $set_title = false;
                    } else {
                        continue;
                    }
                }
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
                }

                $mform->addElement($field->datatype, $field->name, $this->try_get_string($field->name, 'block_opencast'), $param, $attributes);
                
                if ($field->datatype == 'text') {
                    $mform->setType($field->name, PARAM_TEXT);
                }

                if ($field->required) {
                    $mform->addRule($field->name, get_string('required'), 'required');
                }     
                $mform->setAdvanced($field->name, !$field->required);
            }
            if ($set_title) {
                $mform->addElement('text', 'title', get_string('title', 'block_opencast'));
                $mform->addRule('title', get_string('required'), 'required');
                $mform->setType('title', PARAM_TEXT);
            }
            $mform->addElement('date_time_selector', 'startDate', get_string('date', 'block_opencast'));
            $mform->setAdvanced('startDate');

        $mform->closeHeaderBefore('upload_filepicker');
        

        $mform->addElement('header', 'upload_filepicker', get_string('upload', 'block_opencast'));

            $videotypescfg = get_config('block_opencast', 'uploadfileextensions');
            if (empty($videotypescfg)) {
                // Fallback. Use Moodle defined video file types.
                $videotypes = ['video'];
            } else {
                $videotypes = [];
                foreach (explode(',', $videotypescfg) as $videotype) {
                    if (empty($videotype)) {
                        continue;
                    }
                    $videotypes[] = $videotype;
                }
            }

            $mform->addElement('filepicker', 'video_presenter',
                get_string('presenter', 'block_opencast'), null, ['accepted_types' => $videotypes]);
            $mform->addElement('static', 'presenterdesc', null, get_string('presenterdesc', 'block_opencast'));

            $mform->addElement('filepicker', 'video_presentation',
                get_string('presentation', 'block_opencast'), null, ['accepted_types' => $videotypes]);
            $mform->addElement('static', 'presentationdesc', null, get_string('presentationdesc', 'block_opencast'));

            $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
            $mform->setType('courseid', PARAM_INT);
        
        $mform->closeHeaderBefore('buttonar');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $presenter_file = $this->get_draft_files('video_presenter');
        $presentation_file = $this->get_draft_files('video_presentation');

        if (!$presenter_file && !$presentation_file) {
            $errors['video_presenter'] =  get_string('emptyvideouploaderror', 'block_opencast');
            $errors['video_presentation'] =  get_string('emptyvideouploaderror', 'block_opencast');
        }
        
        return $errors;
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

}
