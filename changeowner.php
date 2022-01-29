<?php

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $USER;

$identifier = required_param('identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/changeowner.php',
    array('identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);
$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

require_login($courseid, false);
$coursecontext = context_course::instance($courseid);
course_require_view_participants($coursecontext);

$apibridge = \block_opencast\local\apibridge::get_instance($ocinstanceid);

// Verify that current user is the owner.
if (!$apibridge->is_owner($courseid, $identifier, $USER->id)) {
    throw new moodle_exception(get_string('userisntowner', 'block_opencast'));
} else {
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('pluginname', 'block_opencast'));
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
    $PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
    $PAGE->navbar->add(get_string('changeowner', 'block_opencast'), $baseurl);

    $userselector = new block_opencast_enrolled_user_selector('ownerselect',
        array('context' => $coursecontext, 'multiselect' => false, 'exclude' => [$USER->id]));

    $changeownerform = new \block_opencast\local\changeowner_form(null,
        array('courseid' => $courseid, 'identifier' => $identifier,
            'ocinstanceid' => $ocinstanceid, 'userselector' => $userselector));

    if ($changeownerform->is_cancelled()) {
        redirect($redirecturl);
    }

    if ($data = $changeownerform->get_data()) {
        $newowner = $userselector->get_selected_user();
        if (!$newowner) {
            redirect($baseurl, get_string('nouserselected', 'block_opencast'));
        }

        $success = $apibridge->set_owner($courseid, $identifier, $newowner->id);
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

