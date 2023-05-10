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
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$workflowid = required_param('workflowid', PARAM_ALPHANUMEXT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

require_login($courseid, false);

$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:startworkflow', $coursecontext);

$apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);
$workflow = $apibridge->get_workflow_definition($workflowid);
/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');
if ($workflow) {
    // Display form.
    $context = new \stdClass();
    $context->language = $CFG->lang;
    $context->has_config_panel = !empty($workflow->configuration_panel);
    $context->config_panel = $renderer->close_tags_in_html_string($workflow->configuration_panel);
    $context->parent_url = (new moodle_url('/blocks/opencast/workflowsettings.php'))->out();
    $context->parent_origin = $CFG->wwwroot;

    echo $OUTPUT->render_from_template('block_opencast/workflow_settings_opencast', $context);
}
