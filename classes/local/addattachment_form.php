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
 * Add attachment form.
 *
 * @package    block_opencast
 * @copyright  2020 Tim Schroeder, RWTH Aachen University
 * @author     Tim Schroeder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class addattachment_form extends \moodleform {

    public function definition() {

        $mform = $this->_form;

        $field = $this->_customdata['attachmentfield'];
        $filetypes = [];
        if (empty($field->filetypes)) {
            $filetypes = '*';
        } else {
            foreach (explode(',', $field->filetypes) as $filetype) {
                if (empty($filetype)) {
                    continue;
                }
                // Make sure extension begins with a dot.
                $filetype = '.' . trim($filetype, '.');
                $filetypes[] = $filetype;
            }
        }
        $attributes = [];
        $attributes['accepted_types'] = $filetypes;
        $attributes['maxfiles'] = 1;
        $attributes['subdirs'] = 0;

        $mform->addElement('filepicker', 'itemid', $this->try_get_string($field->name, 'block_opencast'), null, $attributes);

        if ($field->required) {
            $mform->addRule('itemid', get_string('required'), 'required');
        }

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'video_identifier', $this->_customdata['identifier']);
        $mform->setType('video_identifier', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'attachment', $field->name);
        $mform->setType('attachment', PARAM_ALPHANUMEXT);

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

}
