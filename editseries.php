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

$baseurl = new moodle_url('/blocks/opencast/editseries.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('editseriesforcourse', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:defineseriesforcourse', $coursecontext);

$editseriesform = new \block_opencast\local\editseries_form(null, array('courseid' => $courseid));

$apibridge = \block_opencast\local\apibridge::get_instance();

if ($editseriesform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $editseriesform->get_data()) {
    if ($data->seriesid) {
        if ($apibridge->ensure_series_is_valid($data->seriesid)) {
            $apibridge->update_course_series($courseid, $data->seriesid);
            // Update course series.
            redirect($redirecturl, get_string('seriesidsaved', 'block_opencast'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            // Invalid series id.
            redirect($redirecturl, get_string('seriesidnotvalid', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        $apibridge->unset_course_series($courseid);
        redirect($redirecturl, get_string('seriesidunset', 'block_opencast'), null, \core\output\notification::NOTIFY_SUCCESS);

    }
}

$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editseriesforcourse', 'block_opencast'));

$editseriesform->display();
echo $OUTPUT->footer();
