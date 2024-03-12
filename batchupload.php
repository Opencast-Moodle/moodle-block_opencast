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
 * Allows users to upload videos in batch.
 *
 * @package    block_opencast
 * @copyright  2024 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\batchupload_form;
use block_opencast\local\apibridge;
use block_opencast\local\file_deletionmanager;
use block_opencast\local\upload_helper;
use core\output\notification;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $USER, $SITE, $DB;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$series = optional_param('series', null, PARAM_ALPHANUMEXT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurlparams = [
    'ocinstanceid' => $ocinstanceid,
    'courseid' => $courseid,
];
if ($series) {
    $baseurlparams['series'] = $series;
}
$baseurl = new moodle_url('/blocks/opencast/batchupload.php', $baseurlparams);

$PAGE->set_url($baseurl);

if ($courseid == $SITE->id && $series) {
    $redirecturl = new moodle_url('/blocks/opencast/overview_videos.php', ['series' => $series,
        'ocinstanceid' => $ocinstanceid, ]);
} else {
    $redirecturl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
}

require_login($courseid, false);

// Check if the setting is on.
if (empty(get_config('block_opencast', 'batchuploadenabled_' . $ocinstanceid))) {
    throw new moodle_exception('batchupload_errornotenabled', 'block_opencast', $redirecturl);
}

// Use block context for this page to ignore course file upload limit.
$pagecontext = upload_helper::get_opencast_upload_context($courseid);
$PAGE->set_context($pagecontext);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('batchupload', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('batchupload', 'block_opencast'), $baseurl);

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
        $coursecontext = context_course::instance($record->courseid, IGNORE_MISSING);
        if ($coursecontext && has_capability('block/opencast:addvideo', $coursecontext)) {
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

$batchmetadatacatalog = upload_helper::get_opencast_metadata_catalog_batch($ocinstanceid);

$userdefaultsrecord = $DB->get_record('block_opencast_user_default', ['userid' => $USER->id]);
$userdefaults = $userdefaultsrecord ? json_decode($userdefaultsrecord->defaults, true) : [];
$usereventdefaults = (!empty($userdefaults['event'])) ? $userdefaults['event'] : [];

$customdata = [
    'courseid' => $courseid, 'metadata_catalog' => $batchmetadatacatalog,
    'eventdefaults' => $usereventdefaults, 'ocinstanceid' => $ocinstanceid
];
if ($series) {
    $customdata['series'] = $series;
}
$maxuploadsize = (int) get_config('block_opencast', 'uploadfilelimit_' . $ocinstanceid);

$videotypescfg = get_config('block_opencast', 'uploadfileextensions_' . $ocinstanceid);
if (empty($videotypescfg)) {
    // Fallback. Use Moodle defined video file types.
    $videotypes = ['video'];
} else {
    $videotypes = [];
    foreach (explode(',', $videotypescfg) as $videotype) {
        if (empty($videotype)) {
            continue;
        }
        $videotypes[] = $videotype;
    }
}

$filemanageroptions = [
    'accepted_types' => $videotypes,
    'maxbytes' => $maxuploadsize ,
    'subdirs' => false,
    'maxfiles' => -1,
    'mainfile' => false,
];

$customdata['filemanageroptions'] = $filemanageroptions;

$batchuploadform = new batchupload_form(null, $customdata);

if ($batchuploadform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $batchuploadform->get_data()) {
    $itemid = file_get_unused_draft_itemid();
    file_save_draft_area_files(
        $data->batchuploadedvideos,
        $coursecontext->id,
        'block_opencast',
        upload_helper::OC_FILEAREA,
        $itemid,
        $filemanageroptions
    );
    $fs = get_file_storage();
    $batchuploadedfiles = $fs->get_area_files($coursecontext->id, 'block_opencast', upload_helper::OC_FILEAREA, $itemid);

    // Cleanup the drafts.
    $usercontext = \context_user::instance($USER->id);
    $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->batchuploadedvideos, 'id', false);
    if (!empty($draftfiles)) {
        foreach ($draftfiles as $draftfile) {
            file_deletionmanager::fulldelete_file($draftfile);
        }
    }

    // Preparing batch metadata to use.
    $metadata = [];

    if (property_exists($data, 'series')) {
        $metadata[] = [
            'id' => 'isPartOf',
            'value' => $data->series,
        ];
    }

    // Adding data into $metadata based on $metadata_catalog.
    foreach ($batchmetadatacatalog as $field) {
        $id = $field->name;
        if (property_exists($data, $field->name) && $data->$id) {
            if ($field->name == 'subjects') {
                $data->$id = !is_array($data->$id) ? [$data->$id] : $data->$id;
            }
            $obj = [
                'id' => $id,
                'value' => $data->$id,
            ];
            $metadata[] = $obj;
        }
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

    $error = null;
    $totalfiles = count($batchuploadedfiles);
    // Loop through the files and proceed with the upload and cleanup records.
    if (!empty($batchuploadedfiles)) {
        $errorcount = 0;
        foreach ($batchuploadedfiles as $uploadedfile) {
            try {
                if ($uploadedfile->get_filename() === '.') {
                    file_deletionmanager::fulldelete_file($uploadedfile);
                    $totalfiles--;
                    continue;
                }

                $newfileitemid = file_get_unused_draft_itemid();
                $newfilerecord = array(
                    'contextid' => $uploadedfile->get_contextid(),
                    'component' => $uploadedfile->get_component(),
                    'filearea' => $uploadedfile->get_filearea(),
                    'itemid' => $newfileitemid,
                    'timemodified' => time()
                );
                $newfile = $fs->create_file_from_storedfile($newfilerecord, $uploadedfile);
                // Delete the old job.
                file_deletionmanager::fulldelete_file($uploadedfile);
                // Cleaup the batch uploaded files.
                file_deletionmanager::track_draftitemid($newfile->get_contextid(), $newfile->get_itemid());

                $metadataclone = $metadata;
                // Prepare title metadata, extracted from the file name.
                $filename = pathinfo($newfile->get_filename(), \PATHINFO_FILENAME) ?? 'uploaded-video';
                $metadataclone[] = [
                    'id' => 'title',
                    'value' => $filename,
                ];

                // Prepare the upload job options object.
                $options = new stdClass();
                $options->metadata = json_encode($metadataclone);
                $options->presenter = $newfile->get_itemid();

                // Save the upload job.
                upload_helper::save_upload_jobs($ocinstanceid, $courseid, $options, $visibility);
            } catch (moodle_exception $e) {
                $errorcount++;
            }
        }
        if ($errorcount > 0) {
            $obj = new stdClass();
            $obj->error = $errorcount;
            $obj->total = $totalfiles;
            $error = get_string('batchupload_errorsaveuploadjobs', 'block_opencast', $obj);
        }
    } else {
        $error = get_string('batchupload_emptyvideosuploaderror', 'block_opencast');
    }

    $notinicationstatus = !empty($error) ? notification::NOTIFY_ERROR : notification::NOTIFY_SUCCESS;
    $message = $error ?? get_string('batchupload_jobssaved', 'block_opencast', $totalfiles);
    redirect($redirecturl, $message, null, $notinicationstatus);
}

$PAGE->requires->js_call_amd('block_opencast/block_form_handler', 'init');
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('batchupload', 'block_opencast'));
$batchuploadform->display();
echo $OUTPUT->footer();
