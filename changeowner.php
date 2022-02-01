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
 * Change owner of video/series.
 *
 * @package    block_opencast
 * @copyright  2022 Tamara Gunkel, WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

global $PAGE, $OUTPUT, $CFG, $USER, $SITE;

$identifier = required_param('identifier', PARAM_ALPHANUMEXT);
$courseid = optional_param('courseid', $SITE->id, PARAM_INT);
$isseries = optional_param('isseries', false, PARAM_BOOL);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/changeowner.php',
    array('identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid, 'isseries' => $isseries));
$PAGE->set_url($baseurl);

if ($courseid == $SITE->id) {
    $redirecturl = new moodle_url('/blocks/opencast/overview.php', array('ocinstanceid' => $ocinstanceid));
} else {
    $redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
}

require_login($courseid, false);
$coursecontext = context_course::instance($courseid);
$systemcontext = context_system::instance();

if ($courseid == $SITE->id) {
    course_require_view_participants($systemcontext);
} else {
    course_require_view_participants($coursecontext);
}

$apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

if ($isseries) {
    $series = $apibridge->get_series_by_identifier($identifier, true);
    if (!$series) {
        redirect($redirecturl, get_string('series_does_not_exist_admin', 'block_opencast', $identifier), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    $title = $series->title;
    $acls = $series->acl;

} else {
    $video = $apibridge->get_opencast_video($identifier, false, true);

    if ($video->error) {
        redirect($redirecturl, get_string('failedtogetvideo', 'block_opencast'), null,
            \core\output\notification::NOTIFY_ERROR);
    } else {
        $title = $video->video->title;
        $acls = $video->video->acl;
    }
}

// Verify that current user is the owner or is admin.
if (!$apibridge->is_owner($acls, $USER->id, $courseid) &&
    !has_capability('block/opencast:canchangeownerforallvideos', $systemcontext)) {
    throw new moodle_exception(get_string('userisntowner', 'block_opencast'));
} else {
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('pluginname', 'block_opencast'));
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
    $PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
    $PAGE->navbar->add(get_string('changeowner', 'block_opencast'), $baseurl);

    $userselector = new block_opencast_enrolled_user_selector('ownerselect',
        array('context' => $coursecontext, 'multiselect' => false));

    $changeownerform = new \block_opencast\local\changeowner_form(null,
        array('courseid' => $courseid, 'title' => $title, 'identifier' => $identifier,
            'ocinstanceid' => $ocinstanceid, 'userselector' => $userselector, 'isseries' => $isseries));

    if ($changeownerform->is_cancelled()) {
        redirect($redirecturl);
    }

    if ($data = $changeownerform->get_data()) {
        $newowner = $userselector->get_selected_user();
        if (!$newowner) {
            redirect($baseurl, get_string('nouserselected', 'block_opencast'));
        }

        $success = $apibridge->set_owner($courseid, $identifier, $newowner->id, $isseries);
        if ($success) {
            redirect($redirecturl, get_string('changingownersuccess', 'block_opencast'));
        } else {
            redirect($baseurl, get_string('changingownerfailed', 'block_opencast'), null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('changeowner', 'block_opencast'));

    $changeownerform->display();
    echo $OUTPUT->footer();
}

