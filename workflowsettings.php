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
 * Settings for the opencast block
 *
 * @package   block_opencast
 * @copyright 2021 Tamara Gunkel, University of Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
global $PAGE, $OUTPUT, $CFG, $DB;
require_once($CFG->libdir . '/adminlib.php');;
require_login();


// Set the URL that should be used to return to this page.
$PAGE->set_url('/blocks/opencast/workflowsettings');
$PAGE->requires->js_call_amd('block_opencast/block_workflow_settings', 'init');

if (has_capability('moodle/site:config', context_system::instance())) {
    admin_externalpage_setup('block_opencast_workflowsettings');

    $mform = new block_opencast\admin_workflows_form();

    if ($data = $mform->get_data()) {
        $apibridge = \block_opencast\local\apibridge::get_instance();
        $workflows = $apibridge->get_existing_workflows(get_config('block_opencast', 'workflow_tag'), false);
        foreach ($workflows as $workflow) {
            $enabled = optional_param('workflow_' . $workflow->identifier, false, PARAM_BOOL);

            if (!$record = $DB->get_record('block_opencast_workflowdefs', ['workflowdefinitionid' => $workflow->identifier])) {
                $entry = new \stdClass();
                $entry->workflowdefinitionid = $workflow->identifier;
                $entry->enabled = $enabled;
                $DB->insert_record('block_opencast_workflowdefs', $entry, false);
            } else {
                $record->enabled = $enabled;
                $DB->update_record('block_opencast_workflowdefs', $record);
            }
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('settings', 'block_opencast'));

    // Load existing setttings.
    if (empty($entry->id)) {
        $entry = new stdClass;
        $entry->id = 0;
    }

    $mform->set_data($entry);
    $mform->display();
    echo $OUTPUT->footer();
}
