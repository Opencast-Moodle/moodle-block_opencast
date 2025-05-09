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
 * Allows users to upload videos.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\addvideo_form;
use block_opencast\local\apibridge;
use block_opencast\local\file_deletionmanager;
use block_opencast\local\upload_helper;
use core\output\notification;
use tool_opencast\local\settings_api;
use block_opencast\local\workflowconfiguration_helper;

require_once('../../config.php');

global $PAGE, $OUTPUT, $CFG, $USER, $SITE, $DB;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$series = optional_param('intoseries', null, PARAM_ALPHANUMEXT);
if ($series && $courseid != $SITE->id) {
    throw new coding_exception('intoseries parameter is not allowed in connection with a non-frontpage course.');
}
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurlparams = [
    'ocinstanceid' => $ocinstanceid,
    'courseid' => $courseid,
];
if ($series) {
    $baseurlparams['intoseries'] = $series;
}
$baseurl = new moodle_url('/blocks/opencast/addvideo.php', $baseurlparams);

$PAGE->set_url($baseurl);

if ($courseid == $SITE->id && $series) {
    $redirecturl = new moodle_url('/blocks/opencast/overview_videos.php', ['series' => $series,
        'ocinstanceid' => $ocinstanceid, ]);
} else {
    $redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
}

require_login($courseid, false);

// Use block context for this page to ignore course file upload limit.
$pagecontext = upload_helper::get_opencast_upload_context($courseid);
$PAGE->set_context($pagecontext);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('addvideo', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('addvideo', 'block_opencast'), $baseurl);

// Capability check.
if ($courseid == $SITE->id) {
    // If upload initiated from overview page, check that capability is given in specific course or ownership.
    if (!$series) {
        redirect(new moodle_url('/blocks/opencast/overview_videos.php', ['ocinstanceid' => $ocinstanceid, 'series' => null]),
            get_string('addvideonotallowed', 'block_opencast'), null,
            notification::NOTIFY_ERROR);
    }

    $records = $DB->get_records('tool_opencast_series', ['series' => $series, 'ocinstanceid' => $ocinstanceid]);
    $haspermission = false;
    foreach ($records as $record) {
        $cc = context_course::instance($record->courseid, IGNORE_MISSING);
        if ($cc && has_capability('block/opencast:addvideo', $cc)) {
            $haspermission = true;
            break;
        }
    }

    if (!$haspermission) {
        // Check if series is owned by user.
        $apibridge = apibridge::get_instance($ocinstanceid);
        $ocseries = $apibridge->get_series_by_identifier($series);
        if (!$ocseries) {
            redirect(new moodle_url('/blocks/opencast/overview_videos.php', ['ocinstanceid' => $ocinstanceid,
                'series' => $series, ]),
                get_string('connection_failure', 'block_opencast'), null,
                notification::NOTIFY_ERROR);
        }

        if (!$apibridge->is_owner($ocseries->acl, $USER->id, $SITE->id)) {
            redirect(new moodle_url('/blocks/opencast/overview_videos.php', ['ocinstanceid' => $ocinstanceid,
                'series' => $series, ]),
                get_string('addvideonotallowed', 'block_opencast'), null,
                notification::NOTIFY_ERROR);
        }
    }
} else {
    $coursecontext = context_course::instance($courseid);
    require_capability('block/opencast:addvideo', $coursecontext);
}

$metadatacatalog = upload_helper::get_opencast_metadata_catalog($ocinstanceid);

$userdefaultsrecord = $DB->get_record('block_opencast_user_default', ['userid' => $USER->id]);
$userdefaults = $userdefaultsrecord ? json_decode($userdefaultsrecord->defaults, true) : [];
$usereventdefaults = (!empty($userdefaults['event'])) ? $userdefaults['event'] : [];

if ($series) {
    $addvideoform = new addvideo_form($PAGE->url,
        ['courseid' => $courseid, 'metadata_catalog' => $metadatacatalog,
            'eventdefaults' => $usereventdefaults, 'ocinstanceid' => $ocinstanceid, 'series' => $series, ]
    );
} else {
    $addvideoform = new addvideo_form($PAGE->url,
        ['courseid' => $courseid, 'metadata_catalog' => $metadatacatalog,
            'eventdefaults' => $usereventdefaults, 'ocinstanceid' => $ocinstanceid, ]
    );
}

if ($addvideoform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $addvideoform->get_data()) {
    $chunkuploadinstalled = class_exists('\local_chunkupload\chunkupload_form_element');

    // Record the user draft area in this context.
    if (!$chunkuploadinstalled || !get_config('tool_opencast', 'enablechunkupload_' . $ocinstanceid) ||
        property_exists($data, 'presenter_already_uploaded') && $data->presenter_already_uploaded) {
        $storedfilepresenter = $addvideoform->save_stored_file('video_presenter', $coursecontext->id,
            'block_opencast', upload_helper::OC_FILEAREA, $data->video_presenter);
    } else {
        $chunkuploadpresenter = $data->video_presenter_chunk;
    }
    if (!$chunkuploadinstalled || !get_config('tool_opencast', 'enablechunkupload_' . $ocinstanceid) ||
        property_exists($data, 'presentation_already_uploaded') && $data->presentation_already_uploaded) {
        $storedfilepresentation = $addvideoform->save_stored_file('video_presentation', $coursecontext->id,
            'block_opencast', upload_helper::OC_FILEAREA, $data->video_presentation);
    } else {
        $chunkuploadpresentation = $data->video_presentation_chunk;
    }

    if (isset($storedfilepresenter) && $storedfilepresenter) {
        file_deletionmanager::track_draftitemid($coursecontext->id, $storedfilepresenter->get_itemid());
    }
    if (isset($storedfilepresentation) && $storedfilepresentation) {
        file_deletionmanager::track_draftitemid($coursecontext->id, $storedfilepresentation->get_itemid());
    }

    // Transcription files.
    $transcriptions = [];
    if (!empty(get_config('tool_opencast', 'transcriptionworkflow_' . $ocinstanceid))) {
        $maxtranscriptionupload = (int)get_config('tool_opencast', 'maxtranscriptionupload_' . $ocinstanceid);
        // If the max upload limit is not set we assume only 1 field set.
        if (!$maxtranscriptionupload || $maxtranscriptionupload < 0) {
            $maxtranscriptionupload = 1;
        }
        for ($transcriptionindex = 0; $transcriptionindex < $maxtranscriptionupload; $transcriptionindex++) {
            $fileelm = "transcription_file_{$transcriptionindex}";
            $flavorelm = "transcription_flavor_{$transcriptionindex}";
            if (property_exists($data, $fileelm) && property_exists($data, $flavorelm)) {
                $storedfile = $addvideoform->save_stored_file($fileelm, $coursecontext->id,
                    'block_opencast', block_opencast\local\attachment_helper::OC_FILEAREA_ATTACHMENT, $data->{$fileelm});
                $flavor = $data->{$flavorelm};
                if (isset($storedfile) && $storedfile) {
                    $transcriptions[] = [
                        'file_itemid' => $storedfile->get_itemid(),
                        'file_id' => $storedfile->get_id(),
                        'file_contenhash' => $storedfile->get_contenthash(),
                        'flavor' => $flavor,
                    ];
                }
            }
        }
    }

    $metadata = [];

    if (property_exists($data, 'series')) {
        $metadata[] = [
            'id' => 'isPartOf',
            'value' => $data->series,
        ];
    }

    $gettitle = true; // Make sure title (required) is added into metadata.

    // Adding data into $metadata based on $metadata_catalog.
    foreach ($metadatacatalog as $field) {
        $id = $field->name;
        if (property_exists($data, $field->name) && $data->$id) {
            if ($field->name == 'title') { // Make sure the title is received!
                $gettitle = false;
            }
            if ($field->name == 'subjects') {
                !is_array($data->$id) ? $data->$id = [$data->$id] : $data->$id = $data->$id;
            }
            $obj = [
                'id' => $id,
                'value' => $data->$id,
            ];
            $metadata[] = $obj;
        }
    }

    // If admin forgets/mistakenly deletes the title from metadata_catalog the system will create a title!
    if ($gettitle) {
        $titleobj = [
            'id' => 'title',
            'value' => $data->title ? $data->title : 'upload-task',
        ];
        $metadata[] = $titleobj;
    }

    $sd = new DateTime("now", new DateTimeZone("UTC"));
    $sd->setTimestamp($data->startDate);
    $startdate = [
        'id' => 'startDate',
        'value' => $sd->format('Y-m-d'),
    ];
    $starttime = [
        'id' => 'startTime',
        'value' => $sd->format('H:i:s') . 'Z',
    ];
    $metadata[] = $startdate;
    $metadata[] = $starttime;

    $options = new stdClass();
    $options->metadata = json_encode($metadata);
    $options->presenter = isset($storedfilepresenter) && $storedfilepresenter ? $storedfilepresenter->get_itemid() : '';
    $options->presentation = isset($storedfilepresentation) && $storedfilepresentation ? $storedfilepresentation->get_itemid() : '';
    $options->chunkupload_presenter = isset($chunkuploadpresenter) ? $chunkuploadpresenter : '';
    $options->chunkupload_presentation = isset($chunkuploadpresentation) ? $chunkuploadpresentation : '';

    // Prepare attachment object.
    $attachments = new stdClass();
    if (isset($transcriptions) && !empty($transcriptions)) {
        $attachments->transcriptions = $transcriptions;
    }
    // Adding attachment object to the options.
    $options->attachments = $attachments;

    // Prepare the visibility object.
    $visibility = new stdClass();
    $visibility->initialvisibilitystatus = !isset($data->initialvisibilitystatus) ?
        block_opencast_renderer::VISIBLE : $data->initialvisibilitystatus;
    $visibility->initialvisibilitygroups = !empty($data->initialvisibilitygroups) ?
        json_encode($data->initialvisibilitygroups) : null;
    // Check if the scheduled visibility is set.
    if (isset($data->enableschedulingchangevisibility) && $data->enableschedulingchangevisibility) {
        $visibility->scheduledvisibilitytime = $data->scheduledvisibilitytime;
        $visibility->scheduledvisibilitystatus = $data->scheduledvisibilitystatus;
        $visibility->scheduledvisibilitygroups = !empty($data->scheduledvisibilitygroups) ?
            json_encode($data->scheduledvisibilitygroups) : null;
    }

    // Prepare user defined workflow configurations if enabled and exist.
    $workflowconfiguration = null;
    $wfconfighelper = workflowconfiguration_helper::get_instance($ocinstanceid);
    if ($configpaneldata = $wfconfighelper->get_userdefined_configuration_data($data)) {
        $workflowconfiguration = json_encode($configpaneldata);
    }

    // Update all upload jobs.
    upload_helper::save_upload_jobs($ocinstanceid, $courseid, $options, $visibility, $workflowconfiguration);
    redirect($redirecturl, get_string('uploadjobssaved', 'block_opencast'), null, notification::NOTIFY_SUCCESS);
}

$PAGE->requires->js_call_amd('block_opencast/block_form_handler', 'init');
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addvideo', 'block_opencast'));
$addvideoform->display();
echo $OUTPUT->footer();
