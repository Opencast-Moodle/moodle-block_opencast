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
 * Delete events - Mass action.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\apibridge;
use tool_opencast\local\settings_api;
use core\output\notification;
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $SITE;

$ismassaction = required_param('ismassaction', PARAM_INT);
$videoids = required_param_array('videoids', PARAM_RAW);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);
$redirectpage = optional_param('redirectpage', null, PARAM_ALPHA);
$series = optional_param('series', null, PARAM_ALPHANUMEXT);

$baseurl = new moodle_url('/blocks/opencast/deleteevent_massaction.php',
    ['identifier' => $identifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
        'redirectpage' => $redirectpage, 'series' => $series, ]);
$PAGE->set_url($baseurl);

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));

if ($redirectpage == 'overviewvideos') {
    $redirecturl = new moodle_url('/blocks/opencast/overview_videos.php', ['ocinstanceid' => $ocinstanceid,
        'series' => $series, ]);
} else if ($redirectpage == 'overview') {
    $redirecturl = new moodle_url('/blocks/opencast/overview.php', ['ocinstanceid' => $ocinstanceid]);
} else {
    $redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
}

$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('deleteevent_massaction', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:deleteevent', $coursecontext);

$opencast = apibridge::get_instance($ocinstanceid);

$failed = [];
$succeeded = [];

foreach ($videoids as $videoid) {
    $video = $opencast->get_opencast_video($videoid);
    $stringobj = new stdClass();
    $stringobj->name = $video->video->title;
    if ($video->video) {
        if ($opencast->trigger_delete_event($video->video->identifier)) {
            $succeeded[] = $video->video->title;
        } else {
            $failed[] = $video->video->title;
        }
        continue;
    }
    $stringobj->reason = get_string('videonotfound', 'block_opencast');
    $failed[] = get_string('videostablemassaction_notification_reasoning', 'block_opencast', $stringobj);
}

$failedtext = '';
if (!empty($failed)) {
    $failedtext = get_string(
        'deleteevent_massaction_notification_failed',
        'block_opencast',
        implode('</li><li>', $failed)
    );
}
$succeededtext = '';
if (!empty($succeeded)) {
    $succeededtext = get_string(
        'deleteevent_massaction_notification_success',
        'block_opencast',
        implode('</li><li>', $succeeded)
    );
}

// If there is no changes, we redirect with warning.
if (empty($succeededtext) && empty($failedtext)) {
    $nochangetext = get_string('deleteevent_massaction_notification_nochange', 'block_opencast');
    redirect($redirecturl, $nochangetext, null, notification::NOTIFY_ERROR);
}

// Redirect with error if no success message is available.
if (empty($succeededtext) && !empty($failedtext)) {
    redirect($redirecturl, $failedtext, null, notification::NOTIFY_ERROR);
}

// Otherwise, notify the error message if exists.
if (!empty($failedtext)) {
    \core\notification::add($failedtext, \core\notification::ERROR);
}

// If hitting here, that means success message exists and we can redirect!
redirect($redirecturl, $succeededtext, null, notification::NOTIFY_SUCCESS);
