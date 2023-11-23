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
 * Manage default form.
 *
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use coding_exception;
use html_writer;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Manage default form.
 *
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class managedefaults_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $CFG, $PAGE;
        // Get the renderer to use its methods.
        $this->renderer = $PAGE->get_renderer('block_opencast');
        $defaultables = $this->_customdata['defaultables'];
        $userdefaults = $this->_customdata['userdefaults'];

        $mform = $this->_form;

        $explanation = html_writer::tag('p', get_string('managedefaultsforuser_desc', 'block_opencast'));
        $mform->addElement('html', $explanation);

        // Event Metadata.
        if (!empty($defaultables->eventmetadata)) {
            $mform->addElement('header', 'metadata', get_string('metadata', 'block_opencast'));
            $mform->setExpanded('metadata', true);
            $usereventdefaults = (!empty($userdefaults['event'])) ? $userdefaults['event'] : [];
            foreach ($defaultables->eventmetadata as $field) {
                $default = (isset($usereventdefaults[$field->name]) ? $usereventdefaults[$field->name] : null);
                $this->generate_element($field, 'event', $default);
            }
        }

        // Series Metadata.
        if (!empty($defaultables->seriesmetadata)) {
            $mform->closeHeaderBefore('metadataseries');
            $mform->addElement('header', 'metadataseries', get_string('metadataseries', 'block_opencast'));
            $mform->setExpanded('metadataseries', true);
            $userseriesdefaults = (!empty($userdefaults['series'])) ? $userdefaults['series'] : [];
            foreach ($defaultables->seriesmetadata as $field) {
                $default = (isset($userseriesdefaults[$field->name]) ? $userseriesdefaults[$field->name] : null);
                $this->generate_element($field, 'series', $default);
            }
        }

        $mform->addElement('hidden', 'ocinstanceid', $this->_customdata['ocinstanceid']);
        $mform->setType('ocinstanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'redirectto', $this->_customdata['redirectto']);
        $mform->setType('redirectto', PARAM_TEXT);

        $mform->closeHeaderBefore('buttonar');

        $this->add_action_buttons(true, get_string('savedefaults', 'block_opencast'));
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
     * @throws coding_exception
     */
    protected function try_get_string($identifier, $component = '', $a = null) {
        if (!get_string_manager()->string_exists($identifier, $component)) {
            return ucfirst($identifier);
        } else {
            return get_string($identifier, $component, $a);
        }
    }

    /**
     * Generates the field element for mform.
     * @param stdClass $field the metadata field.
     * @param string $belongsto decides which default sets the element belongs to.
     * @param string $default the default value that user has already set.
     */
    protected function generate_element($field, $belongsto, $default = null) {
        $mform = $this->_form;
        $param = [];
        $attributes = [];
        $elementname = "{$belongsto}_{$field->name}";
        $ocinstanceid = $this->_customdata['ocinstanceid'];
        if ($field->param_json) {
            $param = $field->datatype == 'static' ? $field->param_json : (array)json_decode($field->param_json);
        }
        if ($field->datatype == 'autocomplete') {
            $attributes = [
                'multiple' => true,
                'placeholder' => get_string('metadata_autocomplete_placeholder', 'block_opencast',
                    $this->try_get_string($field->name, 'block_opencast')),
                'showsuggestions' => true, // If true, admin is able to add suggestion via admin page. Otherwise no suggestions!
                'noselectionstring' => get_string('metadata_autocomplete_noselectionstring', 'block_opencast',
                    $this->try_get_string($field->name, 'block_opencast')),
                'tags' => true,
            ];

            // Check if the metadata_catalog field is creator or contributor, to pass some suggestions.
            if ($field->name == 'creator' || $field->name == 'contributor') {
                // We merge param values with the suggestions, because param is already initialized.
                $param = array_merge($param,
                    autocomplete_suggestion_helper::get_suggestions_for_creator_and_contributor($ocinstanceid));
            }
        }

        // Get the created element back from addElement function, in order to further use its attrs.
        $element = $mform->addElement($field->datatype, $elementname, $this->try_get_string($field->name, 'block_opencast'),
            $param, $attributes);

        // Check if the description is set for the field, to display it as help icon.
        if (isset($field->description) && !empty($field->description)) {
            // Use the renderer to generate a help icon with custom text.
            $element->_helpbutton = $this->renderer->render_help_icon_with_custom_text(
                $this->try_get_string($field->name, 'block_opencast'), $field->description);
        }

        if ($field->datatype == 'text') {
            $mform->setType($elementname, PARAM_TEXT);
        }

        if (!empty($default)) {
            $mform->setDefault($elementname, $default);
        }
    }
}
