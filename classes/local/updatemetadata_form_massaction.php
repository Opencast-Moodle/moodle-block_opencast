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
 * Update metadata form - Mass action.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use moodleform;
use html_writer;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Update metadata form - Mass action.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatemetadata_form_massaction extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;
        $renderer = $PAGE->get_renderer('block_opencast');

        $ocinstanceid = $this->_customdata['ocinstanceid'];

        $videosdatalist = $this->_customdata['videosdatalist'];

        $mform->addElement('hidden', 'ismassaction', 1);
        $mform->setType('ismassaction', PARAM_INT);

        $videoslisthtmlitem = [];
        foreach ($videosdatalist as $videodata) {
            $videoslisthtmlitem[] = $videodata->title;
            if (empty($videodata->error)) {
                $mform->addElement('hidden', 'videoids[]', $videodata->identifier);
            }
        }
        $mform->setType('videoids', PARAM_ALPHANUMEXT);

        if (!empty($videoslisthtmlitem)) {
            $line = html_writer::tag('hr', '');
            $explanation = html_writer::tag('p',
                get_string('massaction_selectedvideos_list', 'block_opencast',
                    implode('</li><li>', $videoslisthtmlitem))
            );
            $mform->addElement('html', $line . $explanation . $line);
        }

        foreach ($this->_customdata['metadata_catalog'] as $field) {
            $param = [];
            $attributes = [];
            if ($field->param_json) {
                $param = $field->datatype == 'static' ? $field->param_json : json_decode($field->param_json, true);
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

            // Apply format_string to each value of select option, to use Multi-Language filters (if any).
            if ($field->datatype == 'select') {
                array_walk($param, function (&$item) {
                    $item = format_string($item);
                });
            }

            $element = $mform->addElement($field->datatype, $field->name, $this->try_get_string($field->name, 'block_opencast'),
                $param, $attributes);

            // Because there is no normal way to disable and enable the autocomplete field,
            // we render a multiple select field as replacement,
            // in order to give the user the impersseion that this field is not yet enabled.
            if ($field->datatype == 'autocomplete') {
                $selectreplacement = $mform->addElement('select', $field->name . '_replacement',
                    $this->try_get_string($field->name, 'block_opencast'),
                    [
                        '' => $attributes['noselectionstring'],
                    ]
                );
                $selectreplacement->setMultiple(true);

                $mform->disabledIf($field->name . '_replacement', $field->name . '_enabled', 'notchecked');
                $mform->hideIf($field->name . '_replacement', $field->name . '_enabled', 'checked');
                $mform->hideIf($field->name, $field->name . '_enabled', 'notchecked');
            }

            // For mass action fields, it is important to use a checkbox "enabled/disabled" for each field,
            // as to prevent unwanted update on other metadata catalogs. Then only ones that are enabled will get updated.
            $mform->addElement('checkbox', $field->name . '_enabled', '', get_string('enable'));
            $mform->setType($field->name . '_enabled', PARAM_INT);
            $mform->disabledIf($field->name, $field->name . '_enabled', 'notchecked');

            // Check if the description is set for the field, to display it as help icon.
            if (isset($field->description) && !empty($field->description)) {
                // Use the renderer to generate a help icon with custom text.
                $element->_helpbutton = $renderer->render_help_icon_with_custom_text(
                    $this->try_get_string($field->name, 'block_opencast'), $field->description);
            }

            if ($field->datatype == 'text') {
                $mform->setType($field->name, PARAM_TEXT);
            }

            if ($field->readonly) {
                $mform->freeze($field->name);
            } else if ($field->required) {
                $mform->addRule($field->name, get_string('required'), 'required');
            }
        }

        // Adding Start Date field as well manually.
        $mform->addElement('date_time_selector', 'startDate', get_string('date', 'block_opencast'));
        $mform->addElement('checkbox', 'startDate_enabled', '', get_string('enable'));
        $mform->setType('startDate_enabled', PARAM_INT);
        $mform->disabledIf('startDate', 'startDate_enabled', 'notchecked');

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'ocinstanceid', $ocinstanceid);
        $mform->setType('ocinstanceid', PARAM_INT);

        if ($this->_customdata['redirectpage']) {
            $mform->addElement('hidden', 'redirectpage', $this->_customdata['redirectpage']);
            $mform->setType('redirectpage', PARAM_ALPHA);
        }
        if ($this->_customdata['series']) {
            $mform->addElement('hidden', 'series', $this->_customdata['series']);
            $mform->setType('series', PARAM_ALPHANUMEXT);
        }

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
     * Validation.
     * We check if there is any enabled field for the update, if not we return errors.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $fieldnames = array_column($this->_customdata['metadata_catalog'], 'name');
        $fieldnames[] = 'startDate';
        $enablefieldnames = array_map(function ($fieldname) {
            return $fieldname . '_enabled';
        }, $fieldnames);
        $enabledfields = array_filter(array_keys($data), function ($fieldname) use ($enablefieldnames) {
            return in_array($fieldname, $enablefieldnames);
        });
        if (empty($enabledfields)) {
            $errors = array_merge($errors,
                array_fill_keys($enablefieldnames, get_string('updatemetadata_massaction_emptyformsubmission', 'block_opencast')));
        }
        return $errors;
    }
}
