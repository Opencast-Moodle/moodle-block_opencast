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

if (has_capability('moodle/site:config', context_system::instance())) {
    $apibridge = \block_opencast\local\apibridge::get_instance();
    $workflowid = required_param('workflowid', PARAM_ALPHANUMEXT);
    $workflow = $apibridge->get_workflow_definition($workflowid);
    if ($workflow) {
        $record = $DB->get_record('block_opencast_workflowdefs', ['workflowdefinitionid' => $workflow->identifier]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Data was submitted.
            $configuration = json_encode($_POST);

            if (!$record) {
                $entry = new \stdClass();
                $entry->workflowdefinitionid = $workflow->identifier;
                $entry->enabled = false;
                $entry->configuration = $configuration;
                $DB->insert_record('block_opencast_workflowdefs', $entry, false);
            } else {
                $record->configuration = $configuration;
                $DB->update_record('block_opencast_workflowdefs', $record);
            }
            $record->configuration = $configuration;
            echo "<p>" . get_string("config_saved", "block_opencast") . "</p>";
        }

        if ($record) {
            // Create JS to set values.
            // Definitely works for checkboxes/radios/numbers. Probably works for other input types as well.
            foreach (json_decode($record->configuration) as $key => $value) {
                if ($value === "true" || $value === "false") {
                    // Might be a checkbox or radio button. Un-/check it.
                    $js .= "$('input[name=\"" . $key . "\"]').prop('checked', " . $value . ");\n";
                } else {
                    $input = "$('input[name=\"" . $key . "\"]')";
                    $js .= 'if($(' . $input . '[0]).prop("type") === "radio"){' . "\n";
                    $js .= "$(\"input[name=" . $key . "]\").prop('checked', false);\n";
                    $js .= "$(\"input[name=" . $key . "][value='" . $value . "']\").prop('checked', true);\n";
                    $js .= "} else {\n";
                    if (is_numeric($value)) {
                        $js .= "$('input[name=\"" . $key . "\"]').val(" . $value . ");\n";
                    } else {
                        $js .= "$('input[name=\"" . $key . "\"]').val('" . $value . "');\n";
                    }
                    $js .= "}";
                }
            }
        }

        // Display form.
        $context = new \stdClass();
        $context->config_panel = $workflow->configuration_panel;
        $context->form_url = (new moodle_url('/blocks/opencast/serveworkflowconfigpanel.php', array('workflowid' => $workflowid)))->out();
        $context->parent_url = (new moodle_url('/blocks/opencast/workflowsettings.php'))->out();
        $context->js = $js;

        echo $OUTPUT->render_from_template('block_opencast/workflow_settings_opencast', $context);
    }
}
