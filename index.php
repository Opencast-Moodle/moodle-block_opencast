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
require_once('../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');

use block_opencast\local\apibridge;
use tool_opencast\local\settings_api;

global $PAGE, $OUTPUT, $CFG, $DB;

$courseid = required_param('courseid', PARAM_INT);
$ocinstanceid = required_param('ocinstanceid', PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

require_login($courseid, false);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:viewunpublishedvideos', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$opencasterror = null;

try {
    $workflows = array();
    if(!empty(get_config('block_opencast', 'workflow_tag_'.$ocinstanceid))) {
        $workflows = $apibridge->get_existing_workflows(get_config('block_opencast', 'workflow_tag_'.$ocinstanceid), false);
    }
} catch (\block_opencast\opencast_connection_exception $e) {
    $opencasterror = $e->getMessage();
}
$workflowsavailable = count($workflows) > 0;

foreach ($workflows as $workflow) {
    if (empty($workflow->title)) {
        $workflowsjs[$workflow->identifier] = array('title' => $workflow->identifier, 'description' => $workflow->description);
    } else {
        $workflowsjs[$workflow->identifier] = array('title' => $workflow->title, 'description' => $workflow->description);
    }
}

$PAGE->requires->js_call_amd('block_opencast/block_index', 'init', [$courseid, $ocinstanceid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));

if(settings_api::num_ocinstances() > 1) {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast') . ': ' . settings_api::get_ocinstance($ocinstanceid)->name);
}
else {
    $PAGE->set_heading(get_string('pluginname', 'block_opencast'));
}

$PAGE->navbar->add(get_string('overview', 'block_opencast'), $baseurl);

// Invalidate Block cache.
cache_helper::invalidate_by_event('viewopencastvideolist', array($courseid));

$apibridge = apibridge::get_instance($ocinstanceid);

// Start the columns and headers array with the (mandatory) start date column.
$columns = array('start_date');
$headers = array('start_date');

// If configured, add the end date column.
if (get_config('block_opencast', 'showenddate_'.$ocinstanceid)) {
    $columns[] = 'end_date';
    $headers[] = 'end_date';
}

// Add the (mandatory) title column.
$columns[] = 'title';
$headers[] = 'title';

// If configured, add the location column.
if (get_config('block_opencast', 'showlocation_'.$ocinstanceid)) {
    $columns[] = 'location';
    $headers[] = 'location';
}

// If configured, add the publication channel column.
if (get_config('block_opencast', 'showpublicationchannels_'. $ocinstanceid)) {
    $columns[] = 'published';
    $headers[] = 'published';
}

// Add the (mandatory) workflow state column.
$columns[] = 'workflow_state';
$headers[] = 'workflow_state';

// If configured, add the visibility column.
$toggleaclroles = (count($apibridge->getroles(0)) !== 0) &&
    (get_config('block_opencast', 'workflow_roles_'. $ocinstanceid) != "") &&
    (get_config('block_opencast', 'aclcontrolafter'. $ocinstanceid) == true);
if ($toggleaclroles) {
    $columns[] = 'visibility';
    $headers[] = 'visibility';
}

// Add the (mandatory) action column.
$columns[] = 'action';
$headers[] = 'action';

// If enabled and working, add the provide column.
if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
    $columns[] = 'provide';
    $headers[] = 'provide';
}

// If enabled and working, add the provide-activity column.
if (\block_opencast\local\activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
    $columns[] = 'provide-activity';
    $headers[] = 'provide';
}

foreach ($headers as $i => $header) {
    if (!empty($header)) {
        $headers[$i] = get_string('h' . $header, 'block_opencast');
    } else {
        $headers[$i] = '';
    }
}

$perpage = optional_param('perpage', 20, PARAM_INT);

/** @var block_opencast_renderer $renderer */
$renderer = $PAGE->get_renderer('block_opencast');

$opencast = \block_opencast\local\apibridge::get_instance($ocinstanceid);
$table = $renderer->create_videos_tables('ignore', $headers, $columns, $baseurl);
$sortcolumns = $table->get_sort_columns();


echo $OUTPUT->header();


// Check if series exists in OC system. Show a error otherwise.
$seriesid = $apibridge->get_stored_seriesid($courseid);
$ocseriesid = $apibridge->get_course_series($courseid);

if ($seriesid && !$ocseriesid) {
    if (has_capability('block/opencast:defineseriesforcourse', $coursecontext)) {
        echo $OUTPUT->notification(get_string('series_does_not_exist_admin', 'block_opencast', $seriesid));
    } else {
        echo $OUTPUT->notification(get_string('series_does_not_exist', 'block_opencast'));
    }
}

if (!$opencasterror && (has_capability('block/opencast:createseriesforcourse', $coursecontext)
        || has_capability('block/opencast:defineseriesforcourse', $coursecontext))) {
    echo $renderer->render_series_settings_actions($ocinstanceid, $courseid);
}


// Section "Upload or record videos".
if (has_capability('block/opencast:addvideo', $coursecontext)) {
    // Show heading and explanation depending if Opencast Studio is enabled.
    if (get_config('block_opencast', 'enable_opencast_studio_link_' . $ocinstanceid)) {
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
    $addvideourl = new moodle_url('/blocks/opencast/addvideo.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
    $addvideobutton = $OUTPUT->single_button($addvideourl, get_string('addvideo', 'block_opencast'), 'get');
    echo html_writer::div($addvideobutton);

    // If Opencast Studio is enabled, show "Record video" button.
    if (get_config('block_opencast', 'enable_opencast_studio_link_' . $ocinstanceid)) {
        $recordvideo = new moodle_url('/blocks/opencast/recordvideo.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
        $recordvideobutton = $OUTPUT->action_link($recordvideo, get_string('recordvideo', 'block_opencast'),
            null, array('class' => 'btn btn-secondary', 'target' => '_blank'));
        echo html_writer::div($recordvideobutton, 'opencast-recordvideo-wrap');
    }

    // If there are upload jobs scheduled, show the upload queue table.
    $videojobs = \block_opencast\local\upload_helper::get_upload_jobs($ocinstanceid, $courseid);
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
if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
    // Fetch existing LTI episode modules for this course.
    $episodemodules = \block_opencast\local\ltimodulemanager::get_modules_for_episodes($courseid);
}

// if theres ony one (unqiue series) do not show todo?
$courseseries = $DB->get_records('tool_opencast_series', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
$seriesvideodata = array_fill_keys(array_column($courseseries, 'series'), null);
foreach ($courseseries as $series) {
    $seriesvideodata[$series->series] = $opencast->get_series_videos($series->series, $sortcolumns);
}

$errors = array_count_values(array_column($seriesvideodata, 'error'));
if (!empty($errors) && $errors[0] == 0) {
    // Show single error.
    echo html_writer::div(get_string('errorgetblockvideos', 'block_opencast', $seriesvideodata->error), 'opencast-bc-wrap');
} else {
    if ($workflowsavailable) {
        echo '<p class="d-none" id="workflowsjson">' . json_encode($workflowsjs) . '</p>';
    }

    if (!empty($errors) && $errors[0] !== count($seriesvideodata)) {
        // Show all series as only some have errors.
        $showseriesinfo = true;
    } else {
        $showseriesinfo = count($courseseries) > 1;
    }

}

// TODO also do for lti.
// TODO use bootstrap badge instead of button
// If enabled and working, add Opencast Activity series module feature.


if ((\block_opencast\local\activitymodulemanager::is_enabled_and_working_for_series($ocinstanceid) &&
        has_capability('block/opencast:addactivity', $coursecontext)) || (
        \block_opencast\local\ltimodulemanager::is_enabled_and_working_for_series($ocinstanceid) &&
        has_capability('block/opencast:addlti', $coursecontext))) {

    // TODO maybe rewrite explaination and show only once for lti
    // Show explanation.
    echo html_writer::tag('p', get_string('addactivity_addbuttonexplanation', 'block_opencast'));

}


foreach ($seriesvideodata as $series => $videodata) {
    if ($showseriesinfo) {
        // Get series title from first video.
        if ($videodata->videos && $videodata->videos[0]) {
            echo $renderer->render_series_intro($videodata->videos[0]->series);
        } else {
            // Try to retrieve name from opencast.
            $ocseries = $apibridge->get_series_by_identifier($series);
            if($ocseries){
                echo $renderer->render_series_intro($ocseries->title);
            }
            else {
                // If that fails use id.
                echo $renderer->render_series_intro($series);
            }
        }

        // If enabled and working, add LTI series module feature.
        if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_series($ocinstanceid) == true) {

            // Fetch existing LTI series module for this course.
            $moduleid = \block_opencast\local\ltimodulemanager::get_module_for_series($ocinstanceid, $courseid, $series);

            // If there is already a LTI series module created in this course.
            if ($moduleid) {

                // Show button to view the LTI series module.
                $viewltiurl = new moodle_url('/mod/lti/view.php', array('id' => $moduleid));
                $viewltibutton = $OUTPUT->single_button($viewltiurl, get_string('addlti_viewbuttontitle', 'block_opencast'), 'get');
                echo html_writer::tag('p', $viewltibutton);

                // If enabled and working, add additional explanation for LTI episodes module feature.
                if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true &&
                    count($videodata->videos) > 0) {
                    echo html_writer::tag('p', get_string('addltiepisode_explanation', 'block_opencast'));
                }

                // If there isn't a LTI series module yet in this course and the user is allowed to add one.
            } else if (has_capability('block/opencast:addlti', $coursecontext)) {

                // Show button to add the LTI series module.
                $addltiurl = new moodle_url('/blocks/opencast/addlti.php', array('ocinstanceid' => $ocinstanceid,'courseid' => $courseid));
                $addltibutton = $OUTPUT->single_button($addltiurl, get_string('addlti_addbuttontitle', 'block_opencast'), 'get');
                echo html_writer::tag('p', $addltibutton);

                // If enabled and working, add additional explanation for LTI episodes module feature.
                if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true &&
                    count($videodata->videos) > 0) {
                    echo html_writer::tag('p', get_string('addltiepisode_explanation', 'block_opencast'));
                }
            }
        }

// If enabled and working, add Opencast Activity series module feature.
        if (\block_opencast\local\activitymodulemanager::is_enabled_and_working_for_series($ocinstanceid) == true) {

            // Fetch existing Opencast Activity series module for this course.
            $moduleid = \block_opencast\local\activitymodulemanager::get_module_for_series($ocinstanceid, $courseid, $series);

            // If there is already a Opencast Activity series module created in this course.
            if ($moduleid) {

                // Show button to view the Opencast Activity series module.
                $viewactivityurl = new moodle_url('/mod/opencast/view.php', array('id' => $moduleid));
                $viewactivitybutton = $OUTPUT->single_button($viewactivityurl,
                    get_string('addactivity_viewbuttontitle', 'block_opencast'), 'get');
                echo html_writer::tag('p', $viewactivitybutton);

                // If enabled and working, add additional explanation for Opencast Activity episodes module feature.
                if (\block_opencast\local\activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true &&
                    count($videodata->videos) > 0) {
                    echo html_writer::tag('p', get_string('addactivityepisode_explanation', 'block_opencast'));
                }

                // If there isn't a Opencast Activity series module yet in this course and the user is allowed to add one.
            } else if (has_capability('block/opencast:addactivity', $coursecontext)) {

                // Show button to add the Opencast Activity series module.
                $addactivityurl = new moodle_url('/blocks/opencast/addactivity.php', array('ocinstanceid' => $ocinstanceid, 'courseid' => $courseid, 'seriesid' => $series));
                $addactivitybutton = $OUTPUT->single_button($addactivityurl,
                    get_string('addactivity_addbuttontitle', 'block_opencast'), 'get');
                echo html_writer::tag('p', $addactivitybutton);

                // If enabled and working, add additional explanation for Opencast Activity episodes module feature.
                if (\block_opencast\local\activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true &&
                    count($videodata->videos) > 0) {
                    echo html_writer::tag('p', get_string('addactivityepisode_explanation', 'block_opencast'));
                }
            }
        }

    }


    if ($videodata->error == 0) {
        $table = $renderer->create_videos_tables('opencast-videos-table-' . $series, $headers, $columns, $baseurl);

        $deletedvideos = $DB->get_records("block_opencast_deletejob", array(), "", "opencasteventid");

    $engageurl = get_config('filter_opencast', 'engageurl_' . $ocinstanceid);

        foreach ($videodata->videos as $video) {

            $row = array();

            // Start date column.
            $row[] = $renderer->render_created($video->start);

            // End date column.
            if (get_config('block_opencast', 'showenddate_'.$ocinstanceid)) {
                if (property_exists($video, 'duration') && $video->duration) {
                    $row[] = userdate(strtotime($video->start) + intdiv($video->duration, 1000),
                        get_string('strftimedatetime', 'langconfig'));
                } else {
                    $row[] = "";
                }
            }

            // Title column.
            if ($engageurl) {
                $row[] = format_text(html_writer::link($engageurl . '/play/' . $video->identifier, $video->title));
            } else {
                $row[] = $video->title;
            }

            // Location column.
            if (get_config('block_opencast', 'showlocation_' . $ocinstanceid)) {
                $row[] = $video->location;
            }

            // Publication channel column.
            if (get_config('block_opencast', 'showpublicationchannels_'. $ocinstanceid)) {
                $row[] = $renderer->render_publication_status($video->publication_status);
            }

            // Workflow state and actions column, depending if the video is currently deleted or not.
            if (array_key_exists($video->identifier, $deletedvideos)) {
                // Workflow state column.
                $row[] = $renderer->render_processing_state_icon("DELETING");

                // Visibility column.
                if ($toggleaclroles) {
                    $row[] = "";
                }

                // Actions column.
                $row[] = "";

                // Provide LTI column.
                if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid)) {
                    $row[] = "";
                }

                // Provide activity column.
                if (\block_opencast\local\activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid)) {
                    $row[] = "";
                }

            } else {
                // Workflow state column.
                $row[] = $renderer->render_processing_state_icon($video->processing_state);

                // Visibility column.
                if ($toggleaclroles) {
                    if ($video->processing_state !== "SUCCEEDED" && $video->processing_state !== "FAILED" &&
                        $video->processing_state !== "STOPPED") {
                        $row[] = "-";
                    } else {
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
                $actions .= $renderer->render_edit_functions($ocinstanceid, $courseid, $video->identifier, $updatemetadata,
                    $workflowsavailable, $coursecontext);

                if (has_capability('block/opencast:downloadvideo', $coursecontext) && $video->is_downloadable) {
                    $actions .= $renderer->render_download_event_icon($ocinstanceid, $courseid, $video);
                }

                if ($opencast->can_delete_event_assignment($video, $courseid)) {
                    $actions .= $renderer->render_delete_event_icon($ocinstanceid, $courseid, $video->identifier);
                }

                if (!empty(get_config('block_opencast', 'support_email_' . $ocinstanceid))) {
                    $actions .= $renderer->render_report_problem_icon($video->identifier);
                }

                $row[] = $actions;


                // Provide column.
                if (\block_opencast\local\ltimodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
                    // Pick existing LTI episode module for this episode.
                    $moduleid = \block_opencast\local\ltimodulemanager::pick_module_for_episode($episodemodules, $courseid,
                        $video->identifier);
                    $ltiicon = '';
                    // If there is already a LTI episode module created for this episode.
                    if ($moduleid) {
                        // Build icon to view the LTI episode module.
                        $ltiicon = $renderer->render_view_lti_episode_icon($moduleid);

                        // If there isn't a LTI episode module yet in this course and the user is allowed to add one.
                    } else if (has_capability('block/opencast:addltiepisode', $coursecontext)) {
                        // Build icon to add the LTI episode module.
                        $ltiicon = $renderer->render_add_lti_episode_icon($ocinstanceid, $courseid, $video->identifier);
                    }

                    // Add icons to row.
                    $row[] = $ltiicon;
                }


                // Provide Opencast Activity episode module column.
                if (\block_opencast\local\activitymodulemanager::is_enabled_and_working_for_episodes($ocinstanceid) == true) {
                    // Pick existing Opencast Activity episode module for this episode.
                    $moduleid = \block_opencast\local\activitymodulemanager::get_module_for_episode($courseid,
                        $video->identifier);

                    $activityicon = '';
                    // If there is already a Opencast Activity episode module created for this episode.
                    if ($moduleid) {
                        // Build icon to view the Opencast Activity episode module.
                        $activityicon = $renderer->render_view_activity_episode_icon($moduleid);

                        // If there isn't a Opencast Activity episode module yet in this course and the user is allowed to add one.
                    } else if (has_capability('block/opencast:addactivityepisode', $coursecontext)) {
                        // Build icon to add the Opencast Activity episode module.
                        $activityicon = $renderer->render_add_activity_episode_icon($ocinstanceid, $courseid, $video->identifier);
                    }

                    // Add icons to row.
                    $row[] = $activityicon;
                }
            }
            $table->add_data($row);
        }
        $table->finish_html();
    } else {
        echo html_writer::div(get_string('errorgetblockvideos', 'block_opencast', $videodata->error), 'opencast-bc-wrap');
    }

}

// If enabled and working, add manual import videos feature.
if (\block_opencast\local\importvideosmanager::is_enabled_and_working_for_manualimport($ocinstanceid) == true) {
    // Check if the user is allowed to import videos.
    if (has_capability('block/opencast:manualimporttarget', $coursecontext)) {
        // Show heading.
        echo $OUTPUT->heading(get_string('importvideos_importheading', 'block_opencast'));

        // Predefine the duplicating events process explanation.
        $processingexplanation = get_string('importvideos_processingexplanation', 'block_opencast');
        // Get import mode from the admin setting.
        $importmode = get_config('block_opencast', 'importmode_' . $ocinstanceid);
        // Check if the import mode is acl change, then we get another explanation.
        if ($importmode == 'acl') {
            // Get explanation for ACL Change approach.
            $processingexplanation = get_string('importvideos_aclprocessingexplanation', 'block_opencast');
        }
        // Show explanation for manual import.
        echo html_writer::tag('p', get_string('importvideos_sectionexplanation', 'block_opencast') . '<br />' . $processingexplanation);


        // Show "Import videos" button.
        $importvideosurl = new moodle_url('/blocks/opencast/importvideos.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
        $importvideosbutton = $OUTPUT->single_button($importvideosurl,
            get_string('importvideos_importbuttontitle', 'block_opencast'), 'get');
        echo html_writer::div($importvideosbutton);
    }
}

if ($opencasterror) {
    \core\notification::error($opencasterror);
}

echo $OUTPUT->footer();
