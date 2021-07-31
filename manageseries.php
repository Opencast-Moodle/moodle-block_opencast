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
 *
 * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\persistent;
use tool_opencast\local\settings_api;
use tool_opencast\seriesmapping;

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $USER, $DB;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$coursecontext = context_course::instance($courseid);
$ocinstanceid = required_param('ocinstanceid', PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/manageseries.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

$PAGE->requires->js_call_amd('block_opencast/block_manage_series', 'init', [$coursecontext->id, $ocinstanceid]);
$PAGE->requires->css('/blocks/opencast/css/tabulator.min.css');
$PAGE->requires->css('/blocks/opencast/css/tabulator_bootstrap4.min.css');

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
if(settings_api::num_ocinstances() > 1) {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast') . ': ' . settings_api::get_ocinstance($ocinstanceid)->name);
}
else {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
}
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('manageseriesforcourse', 'block_opencast'), $baseurl);

// Capability check.
require_capability('block/opencast:defineseriesforcourse', $coursecontext);

$apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);


/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageseriesforcourse', 'block_opencast'));

echo $renderer->render_manage_series_table($ocinstanceid, $courseid);
echo $OUTPUT->footer();
