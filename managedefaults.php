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
 * Manage course default values.
 *
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\managedefaults_form;
use core\output\notification;
use tool_opencast\local\settings_api;

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $DB, $USER;

$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);
$redirectto = optional_param('redirectto', '', PARAM_TEXT);

$baseurl = new moodle_url('/blocks/opencast/managedefaults.php',
    ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]
);
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php',
    ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]
);

if ($redirectto == 'addvideo') {
    $redirecturl = new moodle_url('/blocks/opencast/addvideo.php',
        ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]
    );
}
if ($redirectto == 'manageseries') {
    $redirecturl = new moodle_url('/blocks/opencast/manageseries.php',
        ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid, 'createseries' => 1]
    );
}

require_login($courseid, false);
$PAGE->set_context(context_system::instance());

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('managedefaultsforuser', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('managedefaultsforuser', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$eventmetadata = json_decode(get_config('block_opencast', 'metadata_' . $ocinstanceid));
$seriesmetadata = json_decode(get_config('block_opencast', 'metadataseries_' . $ocinstanceid));

$defaultables = new stdClass();

$eventmetadatadefaultables = array_filter($eventmetadata, function ($metadata) {
    return !empty($metadata->defaultable);
});
if (!empty($eventmetadatadefaultables)) {
    $defaultables->eventmetadata = $eventmetadatadefaultables;
}

$seriesmetadatadefaultables = array_filter($seriesmetadata, function ($metadata) {
    return !empty($metadata->defaultable);
});
if (!empty($seriesmetadatadefaultables)) {
    $defaultables->seriesmetadata = $seriesmetadatadefaultables;
}

$userdefaultsrecord = $DB->get_record('block_opencast_user_default', ['userid' => $USER->id]);
$userdefaults = [];
if ($userdefaultsrecord) {
    $userdefaults = json_decode($userdefaultsrecord->defaults, true);
}

$managedefaultsform = new managedefaults_form(null,
    ['courseid' => $courseid,
        'ocinstanceid' => $ocinstanceid,
        'redirectto' => $redirectto,
        'defaultables' => $defaultables,
        'userdefaults' => $userdefaults,
    ]);

if ($managedefaultsform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $managedefaultsform->get_data()) {
    $eventdefaults = [];
    $seriesdefaults = [];
    foreach ($data as $fieldname => $fieldvalue) {
        if (strpos($fieldname, 'event_') !== false) {
            $name = str_replace('event_', '', $fieldname);
            $eventdefaults[$name] = $fieldvalue;
        }
        if (strpos($fieldname, 'series_') !== false) {
            $name = str_replace('series_', '', $fieldname);
            $seriesdefaults[$name] = $fieldvalue;
        }
    }

    $defaults = [
        'event' => $eventdefaults,
        'series' => $seriesdefaults,
    ];

    if ($userdefaultsrecord) {
        $userdefaultsrecord->defaults = json_encode($defaults);
        $DB->update_record('block_opencast_user_default', $userdefaultsrecord);
    } else {
        $userdefaultsrecord = new stdClass();
        $userdefaultsrecord->userid = $USER->id;
        $userdefaultsrecord->defaults = json_encode($defaults);
        $DB->insert_record('block_opencast_user_default', $userdefaultsrecord);
    }

    redirect($redirecturl, get_string('defaultssaved', 'block_opencast'), null, notification::NOTIFY_SUCCESS);
}
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managedefaultsforuser', 'block_opencast'));
$managedefaultsform->display();
echo $OUTPUT->footer();
