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
 * Upload batch videos form.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use tool_opencast\exception\opencast_api_response_exception;
use block_opencast_renderer;
use coding_exception;
use core\notification;
use html_writer;
use moodle_url;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Upload batch videos form.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class batchupload_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $DB, $PAGE;
        // Get the renderer to use its methods.
        $renderer = $PAGE->get_renderer('block_opencast');
        $ocinstanceid = $this->_customdata['ocinstanceid'];
        $apibridge = apibridge::get_instance($ocinstanceid);
        $eventdefaults = $this->_customdata['eventdefaults'];
        $wfconfighelper = new workflowconfiguration_helper($ocinstanceid);

        $mform = $this->_form;

        // Batch metadata.
        $mform->addElement('header', 'batch_metadata', get_string('batchupload_metadata_header', 'block_opencast'));
        $mform->setExpanded('batch_metadata', true);

        $managedefaultsurl = new moodle_url('/blocks/opencast/managedefaults.php',
            [
                'courseid' => $this->_customdata['courseid'],
                'ocinstanceid' => $ocinstanceid,
                'redirectto' => 'addvideo',
            ]);
        $managedefaultslink = html_writer::link($managedefaultsurl, get_string('managedefaultsforuser', 'block_opencast'));
        $managedefaultsexplation = html_writer::tag('p',
            get_string('managedefaultredirectlinkwithexp', 'block_opencast') . $managedefaultslink);
        $explanation = html_writer::tag('p', get_string('batchupload_metadata_desc', 'block_opencast'));
        $mform->addElement('html', $explanation . $managedefaultsexplation);

        $seriesrecords = $DB->get_records('tool_opencast_series',
            ['courseid' => $this->_customdata['courseid'], 'ocinstanceid' => $ocinstanceid]);
        if ($seriesrecords) {
            $defaultseries = array_search('1', array_column($seriesrecords, 'isdefault', 'series'));
            $seriesoption = [];

            try {
                $seriesrecords = $apibridge->get_multiple_series_by_identifier($seriesrecords);
                foreach ($seriesrecords as $series) {
                    $seriesoption[$series->identifier] = $series->title;
                }
            } catch (opencast_api_response_exception $e) {
                notification::warning($e->getMessage());
                foreach ($seriesrecords as $series) {
                    $seriesoption[$series->series] = $series->series;
                }
            }

            $mform->addElement('select', 'series', get_string('series', 'block_opencast'), $seriesoption);
            $mform->addRule('series', get_string('required'), 'required');
            $mform->setDefault('series', $defaultseries);
        } else if (array_key_exists('series', $this->_customdata) && $this->_customdata['series']) {
            $seriesoption = [];
            try {
                $seriesrecords = $apibridge->get_multiple_series_by_identifier([$this->_customdata['series']]);
                foreach ($seriesrecords as $series) {
                    $seriesoption[$series->identifier] = $series->title;
                }
            } catch (opencast_api_response_exception $e) {
                notification::warning($e->getMessage());
                $seriesoption[$this->_customdata['series']] = $this->_customdata['series'];
            }

            $mform->addElement('select', 'series', get_string('series', 'block_opencast'), $seriesoption);
            $mform->addRule('series', get_string('required'), 'required');
            $mform->setDefault('series', $this->_customdata['series']);
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

            // Converting value to default value if exists in params JSON.
            $configureddefault = null;
            if (isset($param['value'])) {
                $configureddefault = $param['value'];
                // We unset this here, to make it clean.
                unset($param['value']);
            }

            // Get the created element back from addElement function, in order to further use its attrs.
            $element = $mform->addElement($field->datatype, $field->name, $this->try_get_string($field->name, 'block_opencast'),
                $param, $attributes);

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
                if ($field->datatype == 'autocomplete') {
                    $mform->addRule($field->name, get_string('required'), 'required', null, 'client');
                } else {
                    $mform->addRule($field->name, get_string('required'), 'required');
                }
            }
            $mform->setAdvanced($field->name, !$field->required);

            $default = null;
            if (!empty($configureddefault)) {
                $default = $configureddefault;
                // If the default value from the param JSON is used (configured default) and the field is read-only,
                // this indicates that the field should be displayed in read-only mode and the default value must
                // also be included in the metadata catalog.
                if ($field->readonly) {
                    // We now make sure that the read-only field send its value by form submit.
                    $element->setPersistantFreeze(true);
                }
            }

            // We give precedence to the event defaults configured in the course through "Manage default values".
            if (isset($eventdefaults[$field->name])) {
                $default = $eventdefaults[$field->name];
            }

            // In case we have default value.
            if ($default) {
                $mform->setDefault($field->name, $default);
            }
        }

        $mform->addElement('date_time_selector', 'startDate', get_string('date', 'block_opencast'));
        $mform->setAdvanced('startDate');

        // Batch Visibility configuration.
        if (get_config('tool_opencast', 'aclcontrol_' . $ocinstanceid)) {
            // Prepare all required configurations.
            // Check if Workflow is set and the acl control is enabled.
            $allowchangevisibility = false;
            if (get_config('tool_opencast', 'workflow_roles_' . $ocinstanceid) != "" &&
                get_config('tool_opencast', 'aclcontrolafter_' . $ocinstanceid) == true) {
                $allowchangevisibility = true;
            }
            // Check if the teacher should be allowed to restrict the episode to course groups.
            $groups = [];
            $groupvisibilityallowed = false;
            $controlgroupsenabled = get_config('tool_opencast', 'aclcontrolgroup_' . $ocinstanceid);
            // If group restriction is generally enabled, check if there are roles which allow group visibility.
            if ($controlgroupsenabled) {
                $roles = $apibridge->getroles(0);
                foreach ($roles as $role) {
                    if (strpos($role->rolename, '[COURSEGROUPID]') >= 0) {
                        $groupvisibilityallowed = true;
                        $groups = groups_get_all_groups($this->_customdata['courseid']);
                        break;
                    }
                }
            }

            $mform->closeHeaderBefore('batchupload_visibility_header');

            $mform->addElement('header', 'batchupload_visibility_header',
                get_string('batchupload_visibility_header', 'block_opencast'));
            $mform->setExpanded('batchupload_visibility_header', true);

            $explanation = html_writer::tag('p', get_string('batchupload_visibility_desc', 'block_opencast'));
            $mform->addElement('html', $explanation);

            // Initial visibility.
            $intialvisibilityradioarray = [];
            $intialvisibilityradioarray[] = $mform->addElement('radio', 'initialvisibilitystatus',
                get_string('initialvisibilitystatus', 'block_opencast'), get_string('visibility_hide', 'block_opencast'), 0);
            $intialvisibilityradioarray[] = $mform->addElement('radio', 'initialvisibilitystatus',
                '', get_string('visibility_show', 'block_opencast'), 1);
            // We need to remove the group visibility radio button, when there is no group in the course.
            if ($groupvisibilityallowed && !empty($groups)) {
                $intialvisibilityradioarray[] = $mform->addElement('radio', 'initialvisibilitystatus',
                    '', get_string('visibility_group', 'block_opencast'), 2);
            }
            $mform->setDefault('initialvisibilitystatus', block_opencast_renderer::VISIBLE);
            $mform->setType('initialvisibilitystatus', PARAM_INT);

            // Load existing groups.
            if ($groupvisibilityallowed && !empty($groups)) {
                $options = [];
                foreach ($groups as $group) {
                    $options[$group->id] = $group->name;
                }
                $select = $mform->addElement('select', 'initialvisibilitygroups', get_string('groups'), $options);
                $select->setMultiple(true);
                $mform->hideIf('initialvisibilitygroups', 'initialvisibilitystatus', 'neq', 2);
            }

            if ($allowchangevisibility) {
                // Provide a checkbox to enable changing the visibility for later.
                $mform->addElement('checkbox', 'enableschedulingchangevisibility',
                    get_string('enableschedulingchangevisibility', 'block_opencast'),
                    get_string('enableschedulingchangevisibilitydesc', 'block_opencast'));
                $mform->hideIf('scheduledvisibilitytime', 'enableschedulingchangevisibility', 'notchecked');
                $mform->hideIf('scheduledvisibilitystatus', 'enableschedulingchangevisibility', 'notchecked');

                // Scheduled visibility.
                list($waitingtime, $configuredtimespan) = visibility_helper::get_waiting_time($ocinstanceid);
                $scheduledvisibilitytimeelm = $mform->addElement('date_time_selector', 'scheduledvisibilitytime',
                    get_string('scheduledvisibilitytime', 'block_opencast'));
                $scheduledvisibilitytimeelm->_helpbutton = $renderer->render_help_icon_with_custom_text(
                    get_string('scheduledvisibilitytimehi', 'block_opencast'),
                    get_string('scheduledvisibilitytimehi_help', 'block_opencast', $configuredtimespan));
                $mform->setDefault('scheduledvisibilitytime', $waitingtime);

                $radioarray = [];
                $radioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
                    get_string('scheduledvisibilitystatus', 'block_opencast'), get_string('visibility_hide', 'block_opencast'), 0);
                $radioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus', '',
                    get_string('visibility_show', 'block_opencast'), 1);
                // We need to remove the group visibility radio button, we there is no group in the course.
                if ($groupvisibilityallowed && !empty($groups)) {
                    $radioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
                        '', get_string('visibility_group', 'block_opencast'), 2);
                }

                $mform->setDefault('scheduledvisibilitystatus', block_opencast_renderer::HIDDEN);
                $mform->setType('scheduledvisibilitystatus', PARAM_INT);

                // Load existing groups.
                if ($groupvisibilityallowed && !empty($groups)) {
                    $options = [];
                    foreach ($groups as $group) {
                        $options[$group->id] = $group->name;
                    }
                    $select = $mform->addElement('select', 'scheduledvisibilitygroups', get_string('groups'), $options);
                    $select->setMultiple(true);
                    $mform->hideIf('scheduledvisibilitygroups', 'scheduledvisibilitystatus', 'neq', 2);
                    $mform->hideIf('scheduledvisibilitygroups', 'enableschedulingchangevisibility', 'notchecked');
                }
            }
        }

        // Offering workflow configuration panel settings.
        if ($wfconfighelper->can_provide_configuration_panel()) {
            $mform->closeHeaderBefore('configurationpanel_header');
            $mform->addElement('header', 'configurationpanel_header', get_string('configurationpanel_header', 'block_opencast'));
            $mform->setExpanded('configurationpanel_header', true);

            $configpanelexplanation = html_writer::tag('p', get_string('configurationpanelheader_explanation', 'block_opencast'));
            $mform->addElement('html', $configpanelexplanation);

            $renderer->render_configuration_panel_form_elements(
                $mform,
                $wfconfighelper->get_upload_workflow_configuration_panel(),
                $wfconfighelper->get_allowed_upload_configurations()
            );
        }

        // Batch Upload section.
        $mform->closeHeaderBefore('batchupload_form_header');

        $mform->addElement('header', 'batchupload_form_header', get_string('batchupload_form_header', 'block_opencast'));
        $mform->setExpanded('batchupload_form_header', true);

        $explanation = html_writer::tag('p', get_string('batchuploadexplanation', 'block_opencast'));
        $mform->addElement('html', $explanation);

        $mform->addElement(
            'filemanager',
            'batchuploadedvideos',
            get_string('batchupload_filemanager_presenter_title', 'block_opencast'),
            null,
            $this->_customdata['filemanageroptions']
        );

        $mform->addRule('batchuploadedvideos', get_string('required'), 'required');

        // Term of use.
        if (!empty(get_config('tool_opencast', 'termsofuse_' . $ocinstanceid))) {
            $togglespan = '<span class="btn-link" id="termsofuse_toggle">' .
                get_string('termsofuse_accept_toggle', 'block_opencast') . '</span>';

            $mform->addElement('checkbox', 'termsofuse', get_string('termsofuse', 'block_opencast'),
                get_string('termsofuse_accept', 'block_opencast', $togglespan));
            $mform->addRule('termsofuse', get_string('required'), 'required');
            $options['filter'] = false;
            $mform->addElement('html', '<div class="row justify-content-end" id="termsofuse"><div class="col-md-9">' .
                format_text(get_config('tool_opencast', 'termsofuse_' . $ocinstanceid), FORMAT_HTML, $options) . '</div></div>');
        }

        $mform->addElement('hidden', 'ocinstanceid', $this->_customdata['ocinstanceid']);
        $mform->setType('ocinstanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->closeHeaderBefore('buttonar');

        $this->add_action_buttons(true, get_string('batchupload', 'block_opencast'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $files = file_get_drafarea_files($data['batchuploadedvideos'], '/');
        if (empty($files) || empty($files->list)) {
            $errors['batchuploadedvideos'] = get_string('batchupload_emptyvideosuploaderror', 'block_opencast');
        }

        if (isset($data['initialvisibilitystatus']) &&
            $data['initialvisibilitystatus'] == block_opencast_renderer::GROUP &&
            empty($data['initialvisibilitygroups'])) {
            $errors['initialvisibilitystatus'] = get_string('emptyvisibilitygroups', 'block_opencast');
        }

        if (isset($data['enableschedulingchangevisibility']) && $data['enableschedulingchangevisibility']) {
            // Deducting 2 minutes from the time, to let teachers finish the form.
            $customminutes = [
                'minutes' => 2,
                'action' => 'minus',
            ];
            // Get custom allowed scheduled visibility time.
            $waitingtimearray = visibility_helper::get_waiting_time(
                $this->_customdata['ocinstanceid'], $customminutes);
            $allowedscheduledvisibilitytime = $waitingtimearray[0];
            if (intval($data['scheduledvisibilitytime']) < intval($allowedscheduledvisibilitytime)) {
                $errors['scheduledvisibilitytime'] = get_string('scheduledvisibilitytimeerror',
                    'block_opencast', $waitingtimearray[1]);
            }

            if (isset($data['scheduledvisibilitystatus']) &&
                $data['scheduledvisibilitystatus'] == block_opencast_renderer::GROUP &&
                empty($data['scheduledvisibilitygroups'])) {
                $errors['scheduledvisibilitystatus'] = get_string('emptyvisibilitygroups', 'block_opencast');
            }
            // Check whether the scheduled visibility is equal to initial visibility.
            if (intval($data['scheduledvisibilitystatus']) == intval($data['initialvisibilitystatus'])) {
                $haserror = true;
                if ($data['scheduledvisibilitystatus'] == block_opencast_renderer::GROUP) {
                    sort($data['scheduledvisibilitygroups']);
                    sort($data['initialvisibilitygroups']);
                    if ($data['scheduledvisibilitygroups'] != $data['initialvisibilitygroups']) {
                        $haserror = false;
                    }
                }
                if ($haserror) {
                    $errors['enableschedulingchangevisibility'] = get_string('scheduledvisibilitystatuserror', 'block_opencast');
                }
            }
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
     * @throws coding_exception
     */
    protected function try_get_string($identifier, $component = '', $a = null) {
        if (!get_string_manager()->string_exists($identifier, $component)) {
            return ucfirst($identifier);
        } else {
            return get_string($identifier, $component, $a);
        }
    }
}
