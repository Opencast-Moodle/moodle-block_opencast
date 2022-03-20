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

use local_chunkupload\chunkupload_form_element;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Upload video form.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addvideo_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        global $CFG, $DB, $PAGE;
        // Get the renderer to use its methods.
        $renderer = $PAGE->get_renderer('block_opencast');
        $ocinstanceid = $this->_customdata['ocinstanceid'];
        $apibridge = apibridge::get_instance($ocinstanceid);
        $eventdefaults = $this->_customdata['eventdefaults'];

        $usechunkupload = class_exists('\local_chunkupload\chunkupload_form_element')
            && get_config('block_opencast', 'enablechunkupload_' . $ocinstanceid);

        if ($usechunkupload) {
            \MoodleQuickForm::registerElementType('chunkupload',
                "$CFG->dirroot/local/chunkupload/classes/chunkupload_form_element.php",
                'local_chunkupload\chunkupload_form_element');

            $offerchunkuploadalternative = get_config('block_opencast', 'offerchunkuploadalternative_' . $ocinstanceid);
        }

        $mform = $this->_form;

        $mform->addElement('header', 'metadata', get_string('metadata', 'block_opencast'));
        $mform->setExpanded('metadata', true);

        $managedefaultsurl = new \moodle_url('/blocks/opencast/managedefaults.php',
            array(
                'courseid' => $this->_customdata['courseid'],
                'ocinstanceid' => $ocinstanceid,
                'redirectto' => 'addvideo'
            ));
        $managedefaultslink = \html_writer::link($managedefaultsurl, get_string('managedefaultsforuser', 'block_opencast'));
        $managedefaultsexplation = \html_writer::tag('p',
            get_string('managedefaultredirectlinkwithexp', 'block_opencast') . $managedefaultslink);
        $explanation = \html_writer::tag('p', get_string('metadataexplanation', 'block_opencast'));
        $mform->addElement('html', $explanation . $managedefaultsexplation);

        $seriesrecords = $DB->get_records('tool_opencast_series',
            array('courseid' => $this->_customdata['courseid'], 'ocinstanceid' => $ocinstanceid));
        if ($seriesrecords) {
            $defaultseries = array_search('1', array_column($seriesrecords, 'isdefault', 'series'));
            $seriesoption = array();

            try {
                $seriesrecords = $apibridge->get_multiple_series_by_identifier($seriesrecords);
                foreach ($seriesrecords as $series) {
                    $seriesoption[$series->identifier] = $series->title;
                }
            } catch (\block_opencast\opencast_connection_exception $e) {
                \core\notification::warning($e->getMessage());
                foreach ($seriesrecords as $series) {
                    $seriesoption[$series->series] = $series->series;
                }
            }

            $mform->addElement('select', 'series', get_string('series', 'block_opencast'), $seriesoption);
            $mform->addRule('series', get_string('required'), 'required');
            $mform->setDefault('series', $defaultseries);
        } else if (array_key_exists('series', $this->_customdata) && $this->_customdata['series']) {
            $seriesoption = array();
            try {
                $seriesrecords = $apibridge->get_multiple_series_by_identifier(array($this->_customdata['series']));
                foreach ($seriesrecords as $series) {
                    $seriesoption[$series->identifier] = $series->title;
                }
            } catch (\block_opencast\opencast_connection_exception $e) {
                \core\notification::warning($e->getMessage());
                $seriesoption[$this->_customdata['series']] = $this->_customdata['series'];
            }

            $mform->addElement('select', 'series', get_string('series', 'block_opencast'), $seriesoption);
            $mform->addRule('series', get_string('required'), 'required');
            $mform->setDefault('series', $this->_customdata['series']);
        }

        $settitle = true;
        foreach ($this->_customdata['metadata_catalog'] as $field) {
            $param = array();
            $attributes = array();
            if ($field->name == 'title') {
                if ($field->required) {
                    $settitle = false;
                } else {
                    continue;
                }
            }
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
                    'tags' => true
                ];

                // Check if the metadata_catalog field is creator or contributor, to pass some suggestions.
                if ($field->name == 'creator' || $field->name == 'contributor') {
                    // We merge param values with the suggestions, because param is already initialized.
                    $param = array_merge($param,
                        autocomplete_suggestion_helper::get_suggestions_for_creator_and_contributor($ocinstanceid));
                }
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

            if ($field->required) {
                if ($field->datatype == 'autocomplete') {
                    $mform->addRule($field->name, get_string('required'), 'required', null, 'client');
                } else {
                    $mform->addRule($field->name, get_string('required'), 'required');
                }
            }
            $mform->setAdvanced($field->name, !$field->required);
            $default = (isset($eventdefaults[$field->name]) ? $eventdefaults[$field->name] : null);
            if ($default) {
                $mform->setDefault($field->name, $default);
            }
        }
        if ($settitle) {
            $mform->addElement('text', 'title', get_string('title', 'block_opencast'));
            $mform->addRule('title', get_string('required'), 'required');
            $mform->setType('title', PARAM_TEXT);
        }
        $mform->addElement('date_time_selector', 'startDate', get_string('date', 'block_opencast'));
        $mform->setAdvanced('startDate');

        // Event Visibility configuration.
        $mform->closeHeaderBefore('visibility_header');

        $mform->addElement('header', 'visibility_header', get_string('visibilityheader', 'block_opencast'));
        $mform->setExpanded('visibility_header', true);

        $explanation = \html_writer::tag('p', get_string('visibilityheaderexplanation', 'block_opencast'));
        $mform->addElement('html', $explanation);

        // Check if the teacher should be allowed to restrict the episode to course groups.
        $controlgroupsenabled = get_config('block_opencast', 'aclcontrolgroup_' . $ocinstanceid);
        // If group restriction is generally enabled, check if there are roles which allow group visibility.
        if ($controlgroupsenabled) {
            $roles = $apibridge->getroles(0);
            $groupvisibilityallowed = false;
            foreach ($roles as $role) {
                if (strpos($role->rolename, '[COURSEGROUPID]') >= 0) {
                    $groupvisibilityallowed = true;
                    break;
                }
            }
            $groups = groups_get_all_groups($this->_customdata['courseid']);
        } else {
            $groupvisibilityallowed = false;
        }

        // Initial visibility.
        $intialvisibilityradioarray = array();
        $intialvisibilityradioarray[] = $mform->addElement('radio', 'initialvisibilitystatus',
            get_string('initialvisibilitystatus', 'block_opencast'), get_string('visibility_hide', 'block_opencast'), 0);
        $intialvisibilityradioarray[] = $mform->addElement('radio', 'initialvisibilitystatus',
            '', get_string('visibility_show', 'block_opencast'), 1);
        if ($groupvisibilityallowed) {
            $attributes = array();
            if (empty($groups)) {
                $attributes = array('disabled' => true);
            }
            $radioarray[] = $mform->addElement('radio', 'initialvisibilitystatus',
                '', get_string('visibility_group', 'block_opencast'), 2, $attributes);
        }
        $mform->setDefault('initialvisibilitystatus', \block_opencast_renderer::VISIBLE);
        $mform->setType('initialvisibilitystatus', PARAM_INT);

        // Load existing groups.
        if ($groupvisibilityallowed) {
            $options = [];
            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }
            $select = $mform->addElement('select', 'initialvisibilitygroups', get_string('groups'), $options);
            $select->setMultiple(true);
            $mform->hideIf('initialvisibilitygroups', 'initialvisibilitystatus', 'neq', 2);
        }

        $allowchangevisibility = false;
        // Check if Workflow is set and the acl control is enabled.
        if (get_config('block_opencast', 'workflow_roles_' . $ocinstanceid) != "" &&
            get_config('block_opencast', 'aclcontrolafter_' . $ocinstanceid) == true) {
            $allowchangevisibility = true;
        }

        if ($allowchangevisibility) {
            // Provide a checkbox to enable changing the visibility for later.
            $mform->addElement('checkbox', 'enableschedulingchangevisibility',
                get_string('enableschedulingchangevisibility', 'block_opencast'),
                get_string('enableschedulingchangevisibilitydesc', 'block_opencast'));
            $mform->hideIf('scheduledvisibilitytime', 'enableschedulingchangevisibility', 'notchecked');
            $mform->hideIf('scheduledvisibilitystatus', 'enableschedulingchangevisibility', 'notchecked');

            // Scheduled visibility.
            $mform->addElement('date_time_selector', 'scheduledvisibilitytime',
                get_string('scheduledvisibilitytime', 'block_opencast'));
            $mform->addHelpButton('scheduledvisibilitytime', 'scheduledvisibilitytimehi', 'block_opencast');
            $waitingtime = $this->get_waiting_time($ocinstanceid);
            $mform->setDefault('scheduledvisibilitytime', $waitingtime);

            $radioarray = array();
            $radioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
                get_string('scheduledvisibilitystatus', 'block_opencast'), get_string('visibility_hide', 'block_opencast'), 0);
            $radioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus', '',
                get_string('visibility_show', 'block_opencast'), 1);
            if ($groupvisibilityallowed) {
                $attributes = array();
                if (empty($groups)) {
                    $attributes = array('disabled' => true);
                }
                $radioarray[] = $mform->addElement('radio', 'scheduledvisibilitystatus',
                    '', get_string('visibility_group', 'block_opencast'), 2, $attributes);
            }
            $mform->setDefault('scheduledvisibilitystatus', \block_opencast_renderer::HIDDEN);
            $mform->setType('scheduledvisibilitystatus', PARAM_INT);

            // Load existing groups.
            if ($groupvisibilityallowed) {
                $options = [];
                foreach ($groups as $group) {
                    $options[$group->id] = $group->name;
                }
                $select = $mform->addElement('select', 'scheduledvisibilitygroups', get_string('groups'), $options);
                $select->setMultiple(true);
                $mform->hideIf('scheduledvisibilitygroups', 'scheduledvisibilitystatus', 'neq', 2);
            }
        }

        $mform->closeHeaderBefore('upload_filepicker');

        $mform->addElement('header', 'upload_filepicker', get_string('upload', 'block_opencast'));
        $mform->setExpanded('upload_filepicker', true);

        $explanation = \html_writer::tag('p', get_string('uploadexplanation', 'block_opencast'));
        $mform->addElement('html', $explanation);

        $videotypescfg = get_config('block_opencast', 'uploadfileextensions_' . $ocinstanceid);
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

        $maxuploadsize = (int)get_config('block_opencast', 'uploadfilelimit_' . $ocinstanceid);

        $presenterdesc = \html_writer::tag('p', get_string('presenterdesc', 'block_opencast'));
        $mform->addElement('html', $presenterdesc);

        if (!$usechunkupload || $offerchunkuploadalternative) {
            $mform->addElement('filepicker', 'video_presenter',
                get_string('presenter', 'block_opencast'),
                null, ['accepted_types' => $videotypes]);
        }
        if ($usechunkupload) {
            $mform->addElement('chunkupload', 'video_presenter_chunk', get_string('presenter', 'block_opencast'), null,
                array('maxbytes' => $maxuploadsize, 'accepted_types' => $videotypes));
            if ($offerchunkuploadalternative) {
                $mform->addElement('checkbox', 'presenter_already_uploaded',
                    get_string('usedefaultfilepicker', 'block_opencast'));
                $mform->hideIf('video_presenter', 'presenter_already_uploaded', 'notchecked');
                $mform->hideIf('video_presenter_chunk', 'presenter_already_uploaded', 'checked');
            }
        }

        $presentationdesc = \html_writer::tag('p', get_string('presentationdesc', 'block_opencast'));
        $mform->addElement('html', $presentationdesc);

        if (!$usechunkupload || $offerchunkuploadalternative) {
            $mform->addElement('filepicker', 'video_presentation',
                get_string('presentation', 'block_opencast'),
                null, ['accepted_types' => $videotypes]);
        }
        if ($usechunkupload) {
            $mform->addElement('chunkupload', 'video_presentation_chunk', get_string('presentation', 'block_opencast'), null,
                array('maxbytes' => $maxuploadsize, 'accepted_types' => $videotypes));
            if ($offerchunkuploadalternative) {
                $mform->addElement('checkbox', 'presentation_already_uploaded',
                    get_string('usedefaultfilepicker', 'block_opencast'));
                $mform->hideIf('video_presentation', 'presentation_already_uploaded', 'notchecked');
                $mform->hideIf('video_presentation_chunk', 'presentation_already_uploaded', 'checked');
            }
        }

        if (!empty(get_config('block_opencast', 'termsofuse_' . $ocinstanceid))) {
            $togglespan = '<span class="btn-link" id="termsofuse_toggle">' .
                get_string('termsofuse_accept_toggle', 'block_opencast') . '</span>';

            $mform->addElement('checkbox', 'termsofuse', get_string('termsofuse', 'block_opencast'),
                get_string('termsofuse_accept', 'block_opencast', $togglespan));
            $mform->addRule('termsofuse', get_string('required'), 'required');
            $options['filter'] = false;
            $mform->addElement('html', '<div class="row justify-content-end" id="termsofuse"><div class="col-md-9">' .
                format_text(get_config('block_opencast', 'termsofuse_' . $ocinstanceid), FORMAT_HTML, $options) . '</div></div>');
        }

        $mform->addElement('hidden', 'ocinstanceid', $this->_customdata['ocinstanceid']);
        $mform->setType('ocinstanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->closeHeaderBefore('buttonar');

        $this->add_action_buttons(true, get_string('addvideo', 'block_opencast'));
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
        $chunkuploadinstalled = class_exists('\local_chunkupload\chunkupload_form_element');
        if (!$chunkuploadinstalled || !get_config('block_opencast', 'enablechunkupload_' . $this->_customdata['ocinstanceid']) ||
            isset($data['presenter_already_uploaded']) && $data['presenter_already_uploaded']) {
            $presenterfile = $this->get_draft_files('video_presenter');
        } else {
            $presenterfile = isset($data['video_presenter_chunk']) &&
                chunkupload_form_element::is_file_uploaded($data['video_presenter_chunk']);
        }
        if (!$chunkuploadinstalled || !get_config('block_opencast', 'enablechunkupload_' . $this->_customdata['ocinstanceid']) ||
            isset($data['presentation_already_uploaded']) && $data['presentation_already_uploaded']) {
            $presentationfile = $this->get_draft_files('video_presentation');
        } else {
            $presentationfile = isset($data['video_presentation_chunk']) &&
                chunkupload_form_element::is_file_uploaded($data['video_presentation_chunk']);
        }

        if (!$presenterfile && !$presentationfile) {
            $errors['presenter_already_uploaded'] = get_string('emptyvideouploaderror', 'block_opencast');
            $errors['presentation_already_uploaded'] = get_string('emptyvideouploaderror', 'block_opencast');
        }

        // Deducting 2 minutes from the time, to let teachers finish the form.
        $customminutes = [
            'minutes' => 2,
            'action' => 'minus'
        ];
        // Get custom allowed scheduled visibility time.
        $allowedscheduledvisibilitytime = $this->get_waiting_time($this->_customdata['ocinstanceid'], $customminutes);
        if (isset($data['enableschedulingchangevisibility']) && $data['enableschedulingchangevisibility'] &&
            $data['scheduledvisibilitytime'] < $allowedscheduledvisibilitytime) {
            $errors['scheduledvisibilitytime'] = get_string('scheduledvisibilitytimeerror', 'block_opencast');
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

    /**
     * Returns scheduled change visibility waiting time.
     *
     * @param int $ocinstanceid The opencast instance id.
     * @param array $customminutes Custome minutes to be added or deducted on demand.
     * @return int
     */
    protected function get_waiting_time($ocinstanceid, $customminutes = []) {
        $configwaitingtime = get_config('block_opencast', 'aclcontrolwaitingtime_' . $ocinstanceid);
        if (empty($configwaitingtime)) {
            $configwaitingtime = \block_opencast\local\visibility_helper::DEFAULT_WAITING_TIME;
        }
        $waitingtime = strtotime('now') + (intval($configwaitingtime) * 60);
        // Apply custom minute difference.
        if (isset($customminutes['minutes']) && $customminutes['minutes']) {
            $minutes = $customminutes['minutes'];
            $action = isset($customminutes['action']) ? $customminutes['action'] : 'plus';
            switch ($action) {
                case 'minus':
                    $waitingtime -= ($minutes * 60);
                    break;
                case 'plus':
                default:
                    $waitingtime += ($minutes * 60);
            }
        }
        return $waitingtime;
    }
}
