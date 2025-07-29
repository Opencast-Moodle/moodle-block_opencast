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

use block_opencast\local\workflowconfiguration_helper;
use tool_opencast\local\settings_api;

require_once(__DIR__ . '/../../config.php');
global $PAGE, $OUTPUT, $CFG, $DB;
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$workflowid = required_param('workflowid', PARAM_ALPHANUMEXT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

require_login($courseid, false);

$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:startworkflow', $coursecontext);

$workflow = workflowconfiguration_helper::get_filtered_workflow_definition($ocinstanceid, $workflowid);
/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');
if ($workflow) {
    // The JSON config panel takes precedence over the legacy config panel.
    $wfconfigpanel = $workflow->configuration_panel_json_html;
    if (empty($wfconfigpanel)) {
        $wfconfigpanel = $workflow->configuration_panel;
    }
    // Display form.
    $context = new stdClass();
    $context->language = $CFG->lang;
    $context->has_config_panel = !empty($wfconfigpanel);
    $context->config_panel = $renderer->close_tags_in_html_string($wfconfigpanel);
    $context->parent_url = (new moodle_url('/blocks/opencast/workflowsettings.php'))->out();
    $context->parent_origin = $CFG->wwwroot;

    echo $OUTPUT->render_from_template('block_opencast/workflow_settings_opencast', $context);
}
