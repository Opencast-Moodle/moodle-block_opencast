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
 * Page overview.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\activitymodulemanager;
use block_opencast\local\apibridge;
use block_opencast\local\importvideosmanager;
use block_opencast\local\liveupdate_helper;
use block_opencast\local\ltimodulemanager;
use block_opencast\local\massaction_helper;
use block_opencast\local\upload_helper;
use tool_opencast\exception\opencast_api_response_exception;
use core\notification;
use tool_opencast\local\settings_api;
require_once('../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');

global $PAGE, $OUTPUT, $CFG, $DB, $USER, $SITE;

$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = optional_param('ocinstanceid', settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$PAGE->set_url($baseurl);

require_login($courseid, false);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('tool/opencast:viewunpublishedvideos', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$opencasterror = null;

try {
    $workflows = [];
    if (!empty(get_config('tool_opencast', 'workflow_tags_' . $ocinstanceid))) {
        $tags = explode(',', get_config('tool_opencast', 'workflow_tags_' . $ocinstanceid));
        $tags = array_map('trim', $tags);
        $workflows = $apibridge->get_existing_workflows($tags, false);
    }
} catch (opencast_api_response_exception $e) {
    $opencasterror = $e->getMessage();
}
$workflowsavailable = count($workflows) > 0;

foreach ($workflows as $workflow) {
    if (empty($workflow->title)) {
        $workflowsjs[$workflow->identifier] = ['title' => $workflow->identifier, 'description' => $workflow->description];
    } else {
        $workflowsjs[$workflow->identifier] = ['title' => $workflow->title, 'description' => $workflow->description];
    }
}
// Get live update config settings.
$liveupdateenabled = boolval(get_config('tool_opencast', 'liveupdateenabled_' . $ocinstanceid));
$liveupdatereloadtimeout = intval(get_config('tool_opencast', 'liveupdatereloadtimeout_' . $ocinstanceid));
// Apply the default of 3 seconds for the reload timeout, if not set or incorrect.
if ($liveupdatereloadtimeout < 0) {
    $liveupdatereloadtimeout = 3;
}
$liveupdate = [
    'enabled' => $liveupdateenabled,
    'timeout' => $liveupdatereloadtimeout,
];
$PAGE->requires->js_call_amd('block_opencast/block_index', 'init', [$courseid, $ocinstanceid, $coursecontext->id, $liveupdate]);

$PAGE->requires->js_call_amd('block_opencast/block_massaction', 'init',
    [
        $courseid,
        $ocinstanceid,
        massaction_helper::get_js_selectors(),
    ]
);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));

if (settings_api::num_ocinstances() > 1) {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast') . ': ' . settings_api::get_ocinstance($ocinstanceid)->name);
} else {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
}

$PAGE->navbar->add(get_string('overview', 'block_opencast'), $baseurl);

// Invalidate Block cache.
cache_helper::invalidate_by_event('viewopencastvideolist', [$courseid]);

$apibridge = apibridge::get_instance($ocinstanceid);

// Start the columns and headers array with the (mandatory) start date column.
$columns = ['start_date'];
$headers = ['start_date'];

// If configured, add the end date column.
if (get_config('tool_opencast', 'showenddate_' . $ocinstanceid)) {
    $columns[] = 'end_date';
    $headers[] = 'end_date';
}

// Add the (mandatory) title column.
$columns[] = 'title';
$headers[] = 'title';

// If configured, add the location column.
if (get_config('tool_opencast', 'showlocation_' . $ocinstanceid)) {
    $columns[] = 'location';
    $headers[] = 'location';
}

// If configured, add the publication channel column.
if (get_config('tool_opencast', 'showpublicationchannels_' . $ocinstanceid)) {
    $columns[] = 'published';
    $headers[] = 'published';
}

// Add the (mandatory) workflow state column.
$columns[] = 'workflow_state';
$headers[] = 'workflow_state';

// If configured, add the visibility column.
$toggleaclroles = (count($apibridge->getroles(0)) !== 0) &&
    (get_config('tool_opencast', 'workflow_roles_' . $ocinstanceid) != "") &&
    (get_config('tool_opencast', 'aclcontrolafter_' . $ocinstanceid) == true);
if ($toggleaclroles) {
    $columns[] = 'visibility';
    $headers[] = 'visibility';
}

// Add the (mandatory) action column.
$columns[] = 'action';
$headers[] = 'action';

// If enabled and working, add the provide-activity column.
if (activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
    $columns[] = 'provide-activity';
    $headers[] = 'provide';
}

// If enabled and working, add the provide column.
if (ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
    $columns[] = 'provide';

    // Use different string if activits is also enabled.
    if (activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
        $headers[] = 'providelti';
    } else {
        $headers[] = 'provide';
    }
}

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

$massaction = new massaction_helper();

// Mass-Action configuration for update metadata.
if (!$apibridge->can_update_metadata_massaction($courseid)) {
    $massaction->massaction_action_activation(massaction_helper::MASSACTION_UPDATEMETADATA, false);
}

// Mass-Action configuration for delete.
if (!$apibridge->can_delete_massaction($courseid)) {
    $massaction->massaction_action_activation(massaction_helper::MASSACTION_DELETE, false);
}

// Mass-Action configuration for change visibility.
if (!$apibridge->can_change_visibility_massaction($courseid) || !$toggleaclroles) {
    $massaction->massaction_action_activation(massaction_helper::MASSACTION_CHANGEVISIBILITY, false);
}

// Mass-Action configuration for start workflow.
if (!$apibridge->can_start_workflow_massaction($courseid) || !$workflowsavailable) {
    $massaction->massaction_action_activation(massaction_helper::MASSACTION_STARTWORKFLOW, false);
}

// We add the select columns and headers into the beginning of the headers and columns arrays, when mass actions are there!
if ($massaction->has_massactions()) {
    array_unshift($headers, 'selectall');
    array_unshift($columns, 'select');
}

foreach ($headers as $i => $header) {
    // Take care of selectall at first.
    if ($header == 'selectall') {
        $headers[$i] = $massaction->render_master_checkbox();
        continue;
    }

    if (!empty($header)) {
        $headers[$i] = get_string('h' . $header, 'block_opencast');
    } else {
        $headers[$i] = '';
    }
    // We add customized help icons to the status and visibility headers.
    if ($header == 'visibility' || $header == 'workflow_state') {
        // Preparing the context for the template.
        $context = new stdClass();
        // Passing proper flag to the template context to render related data.
        if ($header == 'visibility') {
            $context->visibilityhelpicon = true;
        } else if ($header == 'workflow_state') {
            $context->statushelpicon = true;
        }
        // Render from the template to show Status legend.
        $legendicon = $renderer->render_from_template('block_opencast/table_legend_help_icon', $context);
        $headers[$i] .= $legendicon;
    }
}

$perpage = optional_param('perpage', 20, PARAM_INT);
$opencast = apibridge::get_instance($ocinstanceid);
$table = $renderer->create_videos_tables('ignore', $headers, $columns, $baseurl);
$sortcolumns = $table->get_sort_columns();


echo $OUTPUT->header();


// Check if series exists in OC system. Show a error otherwise.
$seriesid = $apibridge->get_stored_seriesid($courseid);
$ocseriesid = $apibridge->get_default_course_series($courseid);

if ($seriesid && !$ocseriesid) {
    if (has_capability('tool/opencast:importseriesintocourse', $coursecontext)) {
        echo $OUTPUT->notification(get_string('series_does_not_exist_admin', 'block_opencast', $seriesid));
    } else {
        echo $OUTPUT->notification(get_string('series_does_not_exist', 'block_opencast'));
    }
}

if (!$opencasterror && has_capability('tool/opencast:manageseriesforcourse', $coursecontext)) {
    echo $renderer->render_series_settings_actions($ocinstanceid, $courseid);
}

// Manage default values settings action.
if (has_capability('tool/opencast:addvideo', $coursecontext)) {
    echo $renderer->render_defaults_settings_actions($ocinstanceid, $courseid);
}

// Section "Upload or record videos".
if (has_capability('tool/opencast:addvideo', $coursecontext) && $SITE->id != $courseid) {
    // Show heading and explanation depending if Opencast Studio is enabled.
    if (get_config('tool_opencast', 'enable_opencast_studio_link_' . $ocinstanceid)) {
        // Show heading.
        echo $OUTPUT->heading(get_string('uploadrecordvideos', 'block_opencast'));

        // Show explanation.
        echo html_writer::tag('p', get_string('uploadrecordvideosexplanation', 'block_opencast') . '<br />' .
            get_string('uploadprocessingexplanation', 'block_opencast'));

        // If Opencast Studio is not enabled.
    } else {
        // Show heading.
        echo $OUTPUT->heading(get_string('uploadvideos', 'block_opencast'));

        // Show explanation.
        echo html_writer::tag('p', get_string('uploadvideosexplanation', 'block_opencast') . '<br />' .
            get_string('uploadprocessingexplanation', 'block_opencast'));
    }

    // Show "Add video" button.
    $addvideourl = new moodle_url('/blocks/opencast/addvideo.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
    $addvideobutton = $OUTPUT->single_button($addvideourl, get_string('addvideo', 'block_opencast'), 'get');
    echo html_writer::div($addvideobutton);

    // Show "Add videos (batch)" button.
    if (get_config('tool_opencast', 'batchuploadenabled_' . $ocinstanceid)) {
        $batchuploadurl = new moodle_url('/blocks/opencast/batchupload.php',
            ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
        $batchuploadbutton = $OUTPUT->single_button($batchuploadurl, get_string('batchupload', 'block_opencast'), 'get');
        echo html_writer::div($batchuploadbutton, 'opencast-batchupload-wrap');
    }

    // If Opencast Studio is enabled, show "Record video" button.
    if (get_config('tool_opencast', 'enable_opencast_studio_link_' . $ocinstanceid)) {
        $target = '_self';
        // Check for the admin config to set the link target.
        if (get_config('tool_opencast', 'open_studio_in_new_tab_' . $ocinstanceid)) {
            $target = '_blank';
        }

        // If LTI credentials are given, use LTI. If not, directly forward to Opencast studio.
        if (empty($apibridge->get_lti_consumerkey($ocinstanceid))) {
            if (empty(get_config('tool_opencast', 'opencast_studio_baseurl_' . $ocinstanceid))) {
                $endpoint = settings_api::get_apiurl($ocinstanceid);
            } else {
                $endpoint = get_config('tool_opencast', 'opencast_studio_baseurl_' . $ocinstanceid);
            }

            if (strpos($endpoint, 'http') !== 0) {
                $endpoint = 'http://' . $endpoint;
            }

            $seriesid = $apibridge->get_stored_seriesid($courseid, true, $USER->id);
            $studiourlpath = $apibridge->generate_studio_url_path($courseid, $seriesid);
            $url = $endpoint . $studiourlpath;
            $recordvideobutton = $OUTPUT->action_link($url, get_string('recordvideo', 'block_opencast'),
                null, ['class' => 'btn btn-secondary', 'target' => $target]);
            echo html_writer::div($recordvideobutton, 'opencast-recordvideo-wrap');
        } else {
            $recordvideo = new moodle_url('/blocks/opencast/recordvideo.php',
                ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
            $recordvideobutton = $OUTPUT->action_link($recordvideo, get_string('recordvideo', 'block_opencast'),
                null, ['class' => 'btn btn-secondary', 'target' => $target]);
            echo html_writer::div($recordvideobutton, 'opencast-recordvideo-wrap');
        }
    }

    // If there are upload jobs scheduled, show the upload queue table.
    $videojobs = upload_helper::get_upload_jobs($ocinstanceid, $courseid);
    if (count($videojobs) > 0) {
        // Show heading.
        echo $OUTPUT->heading(get_string('uploadqueuetoopencast', 'block_opencast'));

        // Show explanation.
        echo html_writer::tag('p', get_string('uploadqueuetoopencastexplanation', 'block_opencast'));
        echo $renderer->render_upload_jobs($ocinstanceid, $videojobs);
    }
}

echo $OUTPUT->heading(get_string('videosavailable', 'block_opencast'));

// If enabled and working, fetch the data for the LTI episodes feature.
if (ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
    // Fetch existing LTI episode modules for this course.
    $episodemodules = ltimodulemanager::get_modules_for_episodes($ocinstanceid, $courseid);
}

$courseseries = $DB->get_records('tool_opencast_series', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
$seriesvideodata = array_fill_keys(array_column($courseseries, 'series'), null);
$errors = 0;

foreach ($courseseries as $series) {
    $seriesvideodata[$series->series] = $opencast->get_series_videos($series->series, $sortcolumns);
    if ($seriesvideodata[$series->series]->error) {
        $errors += 1;
    }
}

if ($seriesvideodata && $errors == count($seriesvideodata)) {
    // Show single error.
    echo html_writer::div(get_string('errorgetblockvideos', 'block_opencast', reset($seriesvideodata)->error), 'opencast-bc-wrap');
} else {
    if ($workflowsavailable) {
        echo '<p class="d-none" id="workflowsjson">' . json_encode($workflowsjs) . '</p>';

        // Workflow Privacy Notice.
        if (!empty(get_config('tool_opencast', 'swprivacynoticeinfotext_' . $ocinstanceid))) {
            $privacynoticetitle = get_config('tool_opencast', 'swprivacynoticetitle_' . $ocinstanceid);
            if (empty($privacynoticetitle)) {
                $privacynoticetitle = get_string('swprivacynoticedefaulttitle', 'block_opencast');
            }
            $targetedworkflows = [];
            $swprivacynoticewfds = get_config('tool_opencast', 'swprivacynoticewfds_' . $ocinstanceid);
            if (!empty($swprivacynoticewfds)) {
                $targetedworkflows = explode(',', $swprivacynoticewfds);
                $targetedworkflows = array_map('trim', $targetedworkflows);
            }
            $workflowprivacynoticediv = html_writer::start_tag('div', ['class' => 'd-none', 'id' => 'workflowprivacynotice']);
            $workflowprivacynoticediv .= html_writer::tag('p', $privacynoticetitle, ['id' => 'swprivacynoticetitle']);
            $workflowprivacynoticediv .= html_writer::div(
                format_text(get_config('tool_opencast', 'swprivacynoticeinfotext_' . $ocinstanceid), FORMAT_HTML, []),
                '',
                ['id' => 'swprivacynoticeinfotext']
            );
            $workflowprivacynoticediv .= html_writer::tag('p',
                json_encode($targetedworkflows), ['id' => 'swprivacynoticewfds']);
            $workflowprivacynoticediv .= html_writer::end_tag('div');
            echo $workflowprivacynoticediv;
        }
    }
}

// If enabled and working, add Opencast Activity series module feature.

if ((activitymodulemanager::is_enabled_and_working_for_series($ocinstanceid) &&
        has_capability('tool/opencast:addactivity', $coursecontext)) || (
        ltimodulemanager::is_enabled_and_working_for_series($ocinstanceid) &&
        has_capability('tool/opencast:addlti', $coursecontext))) {

    // Show explanation.
    echo html_writer::tag('p', get_string('addactivity_addbuttonexplanation', 'block_opencast'));

    $series = array_filter($seriesvideodata, function ($vd) {
        return !$vd->error;
    });
}


foreach ($seriesvideodata as $series => $videodata) {
    // Try to retrieve name from opencast.
    $ocseries = $apibridge->get_series_by_identifier($series, true);
    $isseriesowner = false;

    if ($ocseries) {
        echo $renderer->render_series_intro($coursecontext, $ocinstanceid, $courseid, $series, $ocseries->title);
        $isseriesowner = $opencast->is_owner($ocseries->acl, $USER->id, $courseid) || !$opencast->has_owner($ocseries->acl);
    } else {
        // If that fails use id.
        echo $renderer->render_series_intro($coursecontext, $ocinstanceid, $courseid, $series, $series);
    }

    if ($videodata->error == 0) {
        $tableid = 'opencast-videos-table-' . $series;
        $table = $renderer->create_videos_tables($tableid, $headers, $columns, $baseurl);
        $deletedvideos = $DB->get_records("block_opencast_deletejob", [], "", "opencasteventid");
        $engageurl = get_config('tool_opencast', 'engageurl_' . $ocinstanceid);

        // To store rows, and use them later, which gives better control over the table.
        $rows = [];
        foreach ($videodata->videos as $video) {

            $isselectable = true;

            $row = [];

            // Start date column.
            $row[] = $renderer->render_created($video->start);

            // End date column.
            if (get_config('tool_opencast', 'showenddate_' . $ocinstanceid)) {
                if (property_exists($video, 'duration') && $video->duration) {
                    $row[] = userdate(strtotime($video->start) + intdiv($video->duration, 1000),
                        get_string('strftimedatetime', 'langconfig'));
                } else {
                    $row[] = "";
                }
            }

            // Title column.
            if ($engageurl) {
                $row[] = html_writer::link(new moodle_url('/blocks/opencast/engageredirect.php',
                    ['identifier' => $video->identifier, 'courseid' => $courseid,
                        'ocinstanceid' => $ocinstanceid, ]), $video->title, ['target' => '_blank']);
            } else {
                $row[] = $video->title;
            }

            // Location column.
            if (get_config('tool_opencast', 'showlocation_' . $ocinstanceid)) {
                $row[] = $video->location;
            }

            // Publication channel column.
            if (get_config('tool_opencast', 'showpublicationchannels_' . $ocinstanceid)) {
                $row[] = $renderer->render_publication_status($video->publication_status);
            }

            // Workflow state and actions column, depending if the video is currently deleted or not.
            if (array_key_exists($video->identifier, $deletedvideos)) {
                $isselectable = false;
                // Workflow state column.
                $row[] = $renderer->render_processing_state_icon("DELETING");

                // Visibility column.
                if ($toggleaclroles) {
                    $row[] = "";
                }

                // Actions column.
                $row[] = "";

                // Provide activity column.
                if (activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid)) {
                    $row[] = "";
                }

                // Provide LTI column.
                if (ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid)) {
                    $row[] = "";
                }

            } else {
                // Workflow state column.
                $icon = $renderer->render_processing_state_icon($video->processing_state);
                // Add live update flag item (hidden input) to the workflow state column.
                if ($liveupdateenabled && $video->processing_state == 'RUNNING') {
                    $icon .= liveupdate_helper::get_liveupdate_processing_hidden_input($video->identifier, $video->title);
                }
                $row[] = $icon;

                // Visibility column.
                if ($toggleaclroles) {
                    if ($video->processing_state !== "SUCCEEDED" && $video->processing_state !== "FAILED" &&
                        $video->processing_state !== "STOPPED") {
                        $row[] = "-";
                    } else {
                        // Should Do Query alcs already at the beginning to avoid second rest call.
                        $visible = $apibridge->is_event_visible($video->identifier, $courseid);
                        $row[] = $renderer->render_change_visibility_icon($ocinstanceid, $courseid, $video->identifier, $visible);
                    }
                }

                $actions = '';
                if ($opencast->can_delete_acl_group_assignment($video, $courseid)) {
                    $actions .= $renderer->render_delete_acl_group_assignment_icon($ocinstanceid, $courseid, $video->identifier);
                }

                // Actions column.
                $updatemetadata = $opencast->can_update_event_metadata($video, $courseid);
                $useeditor = $opencast->can_edit_event_in_editor($video, $courseid);
                $canchangeowner = ($opencast->is_owner($video->acl, $USER->id, $courseid) ||
                        ($isseriesowner && !$opencast->has_owner($video->acl)) ||
                        has_capability('tool/opencastcanchangeownerforallvideos', context_system::instance())) &&
                    !empty(get_config('tool_opencast', 'aclownerrole_' . $ocinstanceid));
                $canmanagetranscriptions = $opencast->can_edit_event_transcription($video, $courseid);
                $actions .= $renderer->render_edit_functions($ocinstanceid, $courseid, $video->identifier, $updatemetadata,
                    $workflowsavailable, $coursecontext, $useeditor, $canchangeowner, $canmanagetranscriptions);

                if ($opencast->can_show_download_button($video, $courseid)) {
                    $actions .= $renderer->render_download_event_icon($ocinstanceid, $courseid, $video);
                }

                if ($opencast->can_show_directaccess_link($video, $courseid)) {
                    $actions .= $renderer->render_direct_link_event_icon($ocinstanceid, $courseid, $video);
                }

                if ($opencast->can_delete_event_assignment($video, $courseid)) {
                    $actions .= $renderer->render_delete_event_icon($ocinstanceid, $courseid, $video->identifier);
                }

                if (!empty(get_config('tool_opencast', 'support_email_' . $ocinstanceid))) {
                    $actions .= $renderer->render_report_problem_icon($video->identifier);
                }

                $row[] = $actions;

                // Provide Opencast Activity episode module column.
                if (activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
                    // Pick existing Opencast Activity episode module for this episode.
                    $moduleid = activitymodulemanager::get_module_for_episode($courseid,
                        $video->identifier, $ocinstanceid);

                    $activityicon = '';
                    // If there is already a Opencast Activity episode module created for this episode.
                    if ($moduleid) {
                        // Build icon to view the Opencast Activity episode module.
                        $activityicon = $renderer->render_view_activity_episode_icon($moduleid);

                        // If there isn't a Opencast Activity episode module yet in this course and the user is allowed to add one.
                    } else if (has_capability('tool/opencast:addactivityepisode', $coursecontext)) {
                        // Build icon to add the Opencast Activity episode module.
                        $activityicon = $renderer->render_add_activity_episode_icon($ocinstanceid, $courseid, $video->identifier);
                    }

                    // Add icons to row.
                    $row[] = $activityicon;
                }

                // Provide column.
                if (ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
                    // Pick existing LTI episode module for this episode.
                    $moduleid = ltimodulemanager::pick_module_for_episode($ocinstanceid,
                        $episodemodules, $courseid, $video->identifier);
                    $ltiicon = '';
                    // If there is already a LTI episode module created for this episode.
                    if ($moduleid) {
                        // Build icon to view the LTI episode module.
                        $ltiicon = $renderer->render_view_lti_episode_icon($moduleid);

                        // If there isn't a LTI episode module yet in this course and the user is allowed to add one.
                    } else if (has_capability('tool/opencast:addltiepisode', $coursecontext)) {
                        // Build icon to add the LTI episode module.
                        $ltiicon = $renderer->render_add_lti_episode_icon($ocinstanceid, $courseid, $video->identifier);
                    }

                    // Add icons to row.
                    $row[] = $ltiicon;
                }

            }

            $selectcheckbox = $massaction->render_item_checkbox($video, $isselectable);
            if (!empty($selectcheckbox)) {
                array_unshift($row, $selectcheckbox);
            }

            $rows[] = $row;
        }

        // Last check to deactivate mass action, if there is nothing to display in the table.
        $activatedmassaction = !empty($rows);
        $massaction->activate_massaction($activatedmassaction);
        $tablecontainerclasses = ['position-relative'];
        if ($activatedmassaction) {
            $tablecontainerclasses[] = massaction_helper::TABLE_CONTAINER_CLASSNAME;
        }
        // Rendering the table containter div.
        echo html_writer::start_div(implode(' ', $tablecontainerclasses));
        // Rendering mass action on top.
        echo $massaction->render_table_mass_actions_select('bulkselect-top', $tableid);

        // Add rows to the table, which initalizes the table starting html.
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $table->add_data($row);
            }
        }
        // Rendering table.
        $table->finish_html();
        // Rendering mass action on bottom.
        echo $massaction->render_table_mass_actions_select('bulkselect-bottom', $tableid);
        // Rendering closing table container div.
        echo html_writer::end_div();
    } else {
        echo html_writer::div(get_string('errorgetblockvideos', 'block_opencast', $videodata->error), 'opencast-bc-wrap');
    }

}

// If enabled and working, add manual import videos feature.
if (importvideosmanager::is_enabled_and_working_for_manualimport($ocinstanceid) == true) {
    // Check if the user is allowed to import videos.
    if (has_capability('tool/opencast:manualimporttarget', $coursecontext)) {
        // Show heading.
        echo $OUTPUT->heading(get_string('importvideos_importheading', 'block_opencast'));

        // Predefine the duplicating events process explanation.
        $processingexplanation = get_string('importvideos_processingexplanation', 'block_opencast');
        // Get import mode from the admin setting.
        $importmode = get_config('tool_opencast', 'importmode_' . $ocinstanceid);
        $renderimport = true;

        // Check if the import mode is acl change, then we get another explanation.
        if ($importmode == 'acl') {
            // Get explanation for ACL Change approach.
            $processingexplanation = get_string('importvideos_aclprocessingexplanation', 'block_opencast');

            // Check if the maximum number of series is already reached.
            $courseseries = $DB->get_records('tool_opencast_series',
                ['ocinstanceid' => $ocinstanceid, 'courseid' => $courseid]);
            if (count($courseseries) >= get_config('tool_opencast', 'maxseries_' . $ocinstanceid)) {
                $renderimport = false;
            }
        }

        // Show explanation for manual import.
        echo html_writer::tag('p', get_string('importvideos_sectionexplanation', 'block_opencast') .
            '<br />' . $processingexplanation);

        if ($renderimport) {
            // Show "Import videos" button.
            $importvideosurl = new moodle_url('/blocks/opencast/importvideos.php',
                ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
            $importvideosbutton = $OUTPUT->single_button($importvideosurl,
                get_string('importvideos_importbuttontitle', 'block_opencast'), 'get');
            echo html_writer::div($importvideosbutton);
        } else {
            echo html_writer::tag('p', get_string('maxseriesreachedimport', 'block_opencast'));
        }
    }
}

if (empty($seriesvideodata)) {
    echo html_writer::tag('p', (get_string('nothingtodisplay', 'block_opencast')));
}

if ($opencasterror) {
    notification::error($opencasterror);
}

echo $OUTPUT->footer();
