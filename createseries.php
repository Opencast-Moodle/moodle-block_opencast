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
 * * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/createseries.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('createseriesforcourse', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:createseriesforcourse', $coursecontext);

$createseriesform = new \block_opencast\local\createseries_form(null, array('courseid' => $courseid));

if ($createseriesform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $createseriesform->get_data()) {
    // Create new series
    $apibridge = \block_opencast\local\apibridge::get_instance();
    if ($apibridge->create_course_series($courseid, $data->seriestitle)) {
        redirect($redirecturl, get_string('seriescreated', 'block_opencast'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($redirecturl, get_string('seriesnotcreated', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
}

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('createseriesforcourse', 'block_opencast'));
$createseriesform->display();
echo $OUTPUT->footer();
