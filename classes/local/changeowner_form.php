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
 * Form for changing the owner of a video.
 *
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 *  Form for changing the owner of a video.
 *
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class changeowner_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;

        // Define mform.
        $mform = $this->_form;
        $ocinstanceid = $this->_customdata['ocinstanceid'];
        $identifier = $this->_customdata['identifier'];

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_opencast', 'importvideos');

        // Add hidden fields for transferring the wizard results and for wizard step processing.
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'ocinstanceid', $ocinstanceid);
        $mform->setType('ocinstanceid', PARAM_INT);
        $mform->addElement('hidden', 'identifier', $identifier);
        $mform->setType('identifier', PARAM_INT);

        $apibridge = apibridge::get_instance($ocinstanceid);
        $video = $apibridge->get_opencast_video($identifier);
        if ($video->error) {
            $notification = $renderer->wizard_error_notification(
                get_string('failedtogetvideo', 'block_opencast'));
            $mform->addElement('html', $notification);
            $mform->addElement('cancel');

            return;
        }

        $notification = $renderer->wizard_intro_notification(
            get_string('changeowner_explanation', 'block_opencast', $video->video->title));
        $mform->addElement('html', $notification);

        $mform->addElement('html', $this->_customdata['userselector']->display(true));

        // Add action buttons.
        $this->add_action_buttons(true, get_string('changeowner', 'block_opencast'));
    }

}
