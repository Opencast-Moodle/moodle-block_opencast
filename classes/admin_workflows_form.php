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
 * Opencast block admin form.
 *
 * @package   block_opencast
 * @copyright 2021 Tamara Gunkel, University of Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use moodleform;
use html_writer;
use moodle_url;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

class admin_workflows_form extends moodleform
{
    protected function definition()
    {
        global $CFG, $PAGE, $DB;
        $mform = $this->_form;
        $renderer = $PAGE->get_renderer('block_opencast');

        $apibridge = \block_opencast\local\apibridge::get_instance();
        $workflows = $apibridge->get_existing_workflows(get_config('block_opencast', 'workflow_tag'), false, true);

        $rendered_workflows = [];

        foreach ($workflows as $workflow) {
            $rendered_workflows[] = $workflow->identifier;
            $dbworkflow = $DB->get_record('block_opencast_workflowdefs', ['workflowdefinitionid' => $workflow->identifier]);
            if (!$dbworkflow) {
                $element = $renderer->render_workflow_admin_checkbox('workflow_' . $workflow->identifier, false,
                    $workflow->title, get_string('setting_workflowdesc', 'block_opencast'), get_string('new_workflowdef', 'block_opencast'));
            } else {
                // TODO what about id if old/new api=
                $element = $renderer->render_workflow_admin_checkbox('workflow_' . $workflow->identifier, $dbworkflow->enabled,
                    $workflow->title, get_string('setting_workflowdesc', 'block_opencast'));
            }

            $mform->addElement('html', $element);

            // Use data class because class attribute cannot be set.
            $mform->addElement('button', 'configure_' . $workflow->identifier, get_string('settings_configure_workflow', 'block_opencast'),
                array('data-class' => 'config_workflow', 'data-id' => $workflow->identifier));
        }

        // Check if there are workflows in the db which are not in Opencast anymore.
        $saved_workflows = $DB->get_records('block_opencast_workflowdefs');
        foreach ($saved_workflows as $workflow) {
            if (!in_array($workflow->workflowdefinitionid, $rendered_workflows)) {
                $element = $renderer->render_workflow_admin_checkbox('workflow_' . $workflow->workflowdefinitionid, $workflow->enabled,
                    $workflow->workflowdefinitionid, get_string('setting_workflowdesc', 'block_opencast'), null,
                    get_string('workflow_outdated', 'block_opencast'));
                $mform->addElement('html', $element);

                // Show delete button.
                // Use data class because class attribute cannot be set. Use JS to style it correctly.
                $mform->addElement('button', 'delete_' . $workflow->id, get_string('workflow_delete', 'block_opencast'),
                    array('data-class' => 'del_workflow', 'data-id' => $workflow->id, 'data-defid' => $workflow->workflowdefinitionid));
            }
        }

        $mform->addElement('submit', 'submitbutton', get_string('submit', 'block_opencast'));
    }
    // TODO maybe make more beautiful
}
