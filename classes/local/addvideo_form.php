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
use local_chunkupload\chunkupload_form_element;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

class addvideo_form extends \moodleform
{

    public function definition() {
        global $CFG;
        $usechunkupload = class_exists('\local_chunkupload\chunkupload_form_element')
            && get_config('block_opencast', 'enablechunkupload');

        if ($usechunkupload) {
            \MoodleQuickForm::registerElementType('chunkupload',
                "$CFG->dirroot/local/chunkupload/classes/chunkupload_form_element.php",
                'local_chunkupload\chunkupload_form_element');
        }


        $mform = $this->_form;

        $mform->addElement('header', 'metadata', get_string('metadata', 'block_opencast'));

        $explanation = \html_writer::tag('p', get_string('metadataexplanation', 'block_opencast'));
        $mform->addElement('html', $explanation);

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
        if ($settitle) {
            $mform->addElement('text', 'title', get_string('title', 'block_opencast'));
            $mform->addRule('title', get_string('required'), 'required');
            $mform->setType('title', PARAM_TEXT);
        }
        $mform->addElement('date_time_selector', 'startDate', get_string('date', 'block_opencast'));
        $mform->setAdvanced('startDate');

        $mform->closeHeaderBefore('upload_filepicker');


        $mform->addElement('header', 'upload_filepicker', get_string('upload', 'block_opencast'));

        $explanation = \html_writer::tag('p', get_string('uploadexplanation', 'block_opencast'));
        $mform->addElement('html', $explanation);

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

        $maxuploadsize = get_config('block_opencast', 'uploadfilelimit');

        $presenterdesc = \html_writer::tag('p', get_string('presenterdesc', 'block_opencast'));
        $mform->addElement('html', $presenterdesc);

        if ($usechunkupload) {
            $mform->addElement('checkbox', 'presenter_already_uploaded',
                get_string('video_already_uploaded', 'block_opencast'));
        }
        $mform->addElement('filepicker', 'video_presenter',
            get_string('presenter', 'block_opencast'),
            null, ['accepted_types' => $videotypes]);
        if ($usechunkupload) {
            $mform->hideIf('video_presenter', 'presenter_already_uploaded', 'notchecked');
            $mform->addElement('chunkupload', 'video_presenter_chunk', get_string('presenter', 'block_opencast'), null,
                array('maxbytes' => $maxuploadsize, 'accepted_types' => $videotypes));
            $mform->hideIf('video_presenter_chunk', 'presenter_already_uploaded', 'checked');
        }

        $presentationdesc = \html_writer::tag('p', get_string('presentationdesc', 'block_opencast'));
        $mform->addElement('html', $presentationdesc);

        if ($usechunkupload) {
            $mform->addElement('checkbox', 'presentation_already_uploaded',
                get_string('video_already_uploaded', 'block_opencast'));
        }
        $mform->addElement('filepicker', 'video_presentation',
            get_string('presentation', 'block_opencast'),
            null, ['accepted_types' => $videotypes]);
        if ($usechunkupload) {
            $mform->hideIf('video_presentation', 'presentation_already_uploaded', 'notchecked');
            $mform->addElement('chunkupload', 'video_presentation_chunk', get_string('presentation', 'block_opencast'), null,
                array('maxbytes' => $maxuploadsize, 'accepted_types' => $videotypes));
            $mform->hideIf('video_presentation_chunk', 'presentation_already_uploaded', 'checked');
        }

        if (!empty(get_config('block_opencast', 'termsofuse'))) {
            $toggle_span = '<span class="btn-link" id="termsofuse_toggle">' . get_string('termsofuse_accept_toggle', 'block_opencast') . '</span>';

            $mform->addElement('checkbox', 'termsofuse', get_string('termsofuse', 'block_opencast'),
                get_string('termsofuse_accept', 'block_opencast', $toggle_span));
            $mform->addRule('termsofuse', get_string('required'), 'required');
            $options['filter'] = false;
            $mform->addElement('html', '<div class="row justify-content-end" id="termsofuse"><div class="col-md-9">' .
                format_text(get_config('block_opencast', 'termsofuse'), FORMAT_HTML, $options) . '</div></div>');
        }

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
        $errors = parent::validation($data, $files);
        $chunkuploadinstalled = class_exists('\local_chunkupload\chunkupload_form_element');
        if (!$chunkuploadinstalled ||
            isset($data['presenter_already_uploaded']) && $data['presenter_already_uploaded']) {
            $presenterfile = $this->get_draft_files('video_presenter');
        } else {
            $presenterfile = isset($data['video_presenter_chunk']) &&
                chunkupload_form_element::is_file_uploaded($data['video_presenter_chunk']);
        }
        if (!$chunkuploadinstalled ||
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
