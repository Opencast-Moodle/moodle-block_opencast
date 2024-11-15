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
 * Update the metadata of selected videos - Mass action.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\apibridge;
use block_opencast\local\updatemetadata_form_massaction;
use block_opencast\local\upload_helper;
use core\output\notification;
use tool_opencast\local\settings_api;
require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $SITE;

require_once($CFG->dirroot . '/repository/lib.php');

$ismassaction = required_param('ismassaction', PARAM_INT);
$videoids = required_param_array('videoids', PARAM_RAW);
$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);
$redirectpage = optional_param('redirectpage', null, PARAM_ALPHA);
$series = optional_param('series', null, PARAM_ALPHANUMEXT);

$baseurl = new moodle_url('/blocks/opencast/updatemetadata_massaction.php',
    ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
        'redirectpage' => $redirectpage, 'series' => $series, ]);
$PAGE->set_url($baseurl);

if ($redirectpage == 'overviewvideos') {
    $redirecturl = new moodle_url('/blocks/opencast/overview_videos.php', ['ocinstanceid' => $ocinstanceid,
        'series' => $series, ]);
} else if ($redirectpage == 'overview') {
    $redirecturl = new moodle_url('/blocks/opencast/overview.php', ['ocinstanceid' => $ocinstanceid]);
} else {
    $redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
}

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('updatemetadata_massaction', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$opencast = apibridge::get_instance($ocinstanceid);
$massactionmetadatacatalog = upload_helper::get_opencast_metadata_catalog_massaction($ocinstanceid);

$videosdatalist = [];

foreach ($videoids as $videoid) {

    // Record the video data for later use.
    $videodata = new stdClass();
    $videodata->identifier = $videoid;
    $videodata->title = $videoid;
    $videodata->detail = null;
    $videodata->error = false;

    $video = $opencast->get_opencast_video($videoid);

    if (!empty($video->error)) {
        $videodata->error = get_string('videonotfound', 'block_opencast');
        $videodata->detail = get_string('updatemetadata_massaction_videoerror', 'block_opencast', $videodata);
        $videosdatalist[] = $videodata;
        continue;
    }

    $videodata->title = $video->video->title;

    if (!$opencast->can_update_event_metadata($video->video, $courseid, false)) {
        $videodata->error = get_string('massaction_videostatusmismatched', 'block_opencast');
        $videodata->detail = get_string('updatemetadata_massaction_videoerror', 'block_opencast', $videodata);
        $videosdatalist[] = $videodata;
        continue;
    }

    // Bring the video data to the top.
    array_unshift($videosdatalist, $videodata);
}

$massactionupdatemetadataform = new updatemetadata_form_massaction(null,
    ['metadata_catalog' => $massactionmetadatacatalog, 'courseid' => $courseid,
        'ocinstanceid' => $ocinstanceid, 'redirectpage' => $redirectpage,
        'videosdatalist' => $videosdatalist, 'series' => $series, ]);

if ($massactionupdatemetadataform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $massactionupdatemetadataform->get_data()) {
    if (confirm_sesskey()) {
        $selectedmetadata = [];
        $metadataids = array_column($massactionmetadatacatalog, 'name');

        // Adding Start Date manually.
        if (!in_array('startDate', $metadataids)) {
            $metadataids[] = 'startDate';
        }

        foreach ($metadataids as $key) {
            // We check if the metadata was allowed/enabled for the change and update.
            if (isset($data->$key) && isset($data->{$key . "_enabled"})) {
                $sd = null;
                if ($key == 'startDate') {
                    $sd = new DateTime("now", new DateTimeZone("UTC"));
                    $sd->setTimestamp($data->startDate);
                    $starttime = [
                        'id' => 'startTime',
                        'value' => $sd->format('H:i:s') . 'Z',
                    ];
                    $selectedmetadata[] = $starttime;
                }
                $contentobj = [
                    'id' => $key,
                    'value' => ($key == 'startDate' && !empty($sd)) ? $sd->format('Y-m-d') : $data->$key,
                ];
                $selectedmetadata[] = $contentobj;
            }
        }

        $failed = [];
        $succeeded = [];
        if (!empty($selectedmetadata)) {
            // All processed video data is included in $videosdatalist variable beforehand!
            $selectedmetadataids = array_column($selectedmetadata, 'id');
            foreach ($videosdatalist as $videodata) {
                // Now we need to get and replace the metadata of each video to make sure it only changes allowed catalogs.
                $metadata = $opencast->get_event_metadata($videodata->identifier, 'dublincore/episode');
                $metadataids = array_column($metadata, 'id');
                $squashedmetadata = [];
                foreach ($metadataids as $index => $id) {
                    $currentcatalog = $metadata[$index];
                    $value = $currentcatalog->value;
                    // If the metadata id exists in the selected ids (is allowed to be updated), we replace the value.
                    if (in_array($id, $selectedmetadataids)) {
                        $value = $selectedmetadata[array_search($id, $selectedmetadataids)]['value'];
                    }
                    $newobj = [
                        'id' => $id,
                        'value' => $value,
                    ];
                    $squashedmetadata[] = $newobj;
                }
                $res = $opencast->update_event_metadata($videodata->identifier, $squashedmetadata);
                if ($res) {
                    $succeeded[] = $videodata->title;
                } else {
                    $failed[] = $videodata->title;
                }
            }
        }

        $failedtext = '';
        if (!empty($failed)) {
            $failedtext = get_string(
                'updatemetadata_massaction_notification_failed',
                'block_opencast',
                implode('</li><li>', $failed)
            );
        }
        $succeededtext = '';
        if (!empty($succeeded)) {
            $succeededtext = get_string(
                'updatemetadata_massaction_notification_succeeded',
                'block_opencast',
                implode('</li><li>', $succeeded)
            );
        }

        // If there is no changes, we redirect with warning.
        if (empty($succeededtext) && empty($failedtext)) {
            $nochangetext = get_string('updatemetadata_massaction_notification_nochange', 'block_opencast');
            redirect($redirecturl, $nochangetext, null, notification::NOTIFY_WARNING);
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
    }
}
$PAGE->requires->js_call_amd('block_opencast/block_form_handler', 'init');
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('updatemetadata_massaction', 'block_opencast'));
$massactionupdatemetadataform->display();
echo $OUTPUT->footer();
