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
 * Renderer for opencast block.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\local\activitymodulemanager;
use block_opencast\local\apibridge;
use block_opencast\local\attachment_helper;
use block_opencast\local\ingest_uploader;
use block_opencast\local\ltimodulemanager;
use block_opencast\local\massaction_helper;
use block_opencast\local\upload_helper;
use block_opencast\local\visibility_helper;
use mod_opencast\local\opencasttype;
use block_opencast\local\liveupdate_helper;
use tool_opencast\local\settings_api;
use tool_opencast\seriesmapping;
use block_opencast\local\workflowconfiguration_helper;
/**
 * Renderer class for block opencast.
 *
 * @package   block_opencast
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_renderer extends plugin_renderer_base {

    /** @var int Video is visible for students */
    const VISIBLE = 1;
    /** @var int Video is visible for some students */
    const MIXED_VISIBILITY = 3;
    /** @var int Video is hidden for students */
    const HIDDEN = 0;
    /** @var int Video is visible for groups of students. */
    const GROUP = 2;

    /**
     * Render the opencast timestamp in moodle standard format.
     *
     * @param string $opencastcreated the timestamp delivered by opencast api call.
     *
     * @return string
     */
    public function render_created($opencastcreated) {
        return userdate(strtotime($opencastcreated), get_string('strftimedatetime', 'langconfig'));
    }

    /**
     * Render the icon for opencast processing state
     *
     * @param string $processingstate
     *
     * @return string HTML code for icon
     */
    public function render_processing_state_icon($processingstate) {
        switch ($processingstate) {

            case 'FAILED' :
                $tooltip = get_string('ocstatefailed', 'block_opencast');
                return $this->output->pix_icon('failed', $tooltip, 'block_opencast',
                    ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $tooltip]);
            case 'PLANNED' :
                $tooltip = get_string('planned', 'block_opencast');
                return $this->output->pix_icon('c/event', get_string('planned', 'block_opencast'), 'moodle',
                    ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $tooltip]);
            case 'CAPTURING' :
                $tooltip = get_string('ocstatecapturing', 'block_opencast');
                return $this->output->pix_icon('capturing', get_string('ocstatecapturing', 'block_opencast'), 'block_opencast',
                    ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $tooltip]);
            case 'NEEDSCUTTING' :
                $tooltip = get_string('ocstateneedscutting', 'block_opencast');
                return $this->output->pix_icon('e/cut', get_string('ocstateneedscutting', 'block_opencast'), 'moodle',
                    ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $tooltip]);
            case 'DELETING' :
                $tooltip = get_string('deleting', 'block_opencast');
                return $this->output->pix_icon('t/delete', get_string('deleting', 'block_opencast'), 'moodle',
                    ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $tooltip]);
            case 'RUNNING' :
            case 'PAUSED' :
                $tooltip = get_string('ocstateprocessing', 'block_opencast');
                return $this->output->pix_icon('i/loading_small', get_string('ocstateprocessing', 'block_opencast'), 'moodle',
                    ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $tooltip]);
            case 'SUCCEEDED' :
            default :
                $tooltip = get_string('ocstatesucceeded', 'block_opencast');
                return $this->output->pix_icon('succeeded', get_string('ocstatesucceeded', 'block_opencast'), 'block_opencast',
                    ['data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $tooltip]);
        }
    }

    /**
     * Render the intro for series on the index page.
     * @param object $coursecontext
     * @param int $ocinstanceid
     * @param int $courseid
     * @param string $seriesid
     * @param string $seriesname
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function render_series_intro($coursecontext, $ocinstanceid, $courseid, $seriesid, $seriesname) {
        $url = null;
        $text = null;
        $addactivitylink = '';
        $addltilink = '';

        if (activitymodulemanager::is_enabled_and_working_for_series($ocinstanceid) == true) {
            // Fetch existing Opencast Activity series module for this series.
            $moduleid = activitymodulemanager::get_module_for_series($ocinstanceid, $courseid, $seriesid);

            if ($moduleid) {
                $url = new moodle_url('/mod/opencast/view.php', ['id' => $moduleid]);
                $text = get_string('addactivity_viewbuttontitle', 'block_opencast');
                $icon = $this->output->pix_icon('play', $text, 'block_opencast');
            } else if (has_capability('block/opencast:addactivity', $coursecontext)) {
                $url = new moodle_url('/blocks/opencast/addactivity.php',
                    ['ocinstanceid' => $ocinstanceid, 'courseid' => $courseid, 'seriesid' => $seriesid]);
                $text = get_string('addactivity_addbuttontitle', 'block_opencast');
                $icon = $this->output->pix_icon('share', $text, 'block_opencast');
            }
            if ($url) {
                $addactivitylink = html_writer::link($url, $icon,
                    ['title' => $text]);
            }
        }

        if (ltimodulemanager::is_enabled_and_working_for_series($ocinstanceid) == true) {
            // Fetch existing LTI series module for this series.
            $moduleid = ltimodulemanager::get_module_for_series($ocinstanceid, $courseid, $seriesid);

            $url = null;
            if ($moduleid) {
                $url = new moodle_url('/mod/lti/view.php', ['id' => $moduleid]);
                $text = get_string('addlti_viewbuttontitle', 'block_opencast');
                $icon = $this->output->pix_icon('play', $text, 'block_opencast');
            } else if (has_capability('block/opencast:addlti', $coursecontext)) {
                $url = new moodle_url('/blocks/opencast/addlti.php',
                    ['ocinstanceid' => $ocinstanceid, 'courseid' => $courseid, 'seriesid' => $seriesid]);
                $text = get_string('addlti_addbuttontitle', 'block_opencast');
                $icon = $this->output->pix_icon('share', $text, 'block_opencast');
            }
            if ($url) {
                $addltilink = html_writer::link($url, $icon,
                    ['title' => $text]);
            }
        }

        $courses = seriesmapping::get_records(['series' => $seriesid, 'ocinstanceid' => $ocinstanceid]);

        if (count($courses) > 1) {
            $tooltip = '';
            foreach ($courses as $course) {
                try {
                    $c = get_course($course->get('courseid'));
                    if ($tooltip) {
                        $tooltip .= '<br>';
                    }
                    $tooltip .= $c->fullname;
                } catch (dml_exception $e) {
                    continue;
                }
            }

            $usedin = html_writer::tag('span', get_string('series_used', 'block_opencast', count($courses)),
                ["class" => "badge badge-secondary mb-4", "data-toggle" => 'tooltip', 'data-placement' => 'top',
                    'title' => $tooltip, 'data-html' => 'true', ]);
            return $this->heading($seriesname, 4, ['mt-4 d-inline-block']) . ' ' .
                $addactivitylink . ' ' . $addltilink . '<br>' . $usedin;
        }

        return $this->heading($seriesname, 4, ['mt-4 mb-4 d-inline-block']) . ' ' . $addactivitylink . ' ' . $addltilink;
    }

    /**
     * Render the link for providing a series as activity.
     * @param stdClass $coursecontext
     * @param int $ocinstanceid
     * @param int $courseid
     * @param int $seriesid
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function render_provide_activity($coursecontext, $ocinstanceid, $courseid, $seriesid) {
        $activitybutton = '';
        $ltibutton = '';

        if (activitymodulemanager::is_enabled_and_working_for_series($ocinstanceid) == true) {
            // Fetch existing Opencast Activity series module for this series.
            $moduleid = activitymodulemanager::get_module_for_series($ocinstanceid, $courseid, $seriesid);

            if ($moduleid) {
                $url = new moodle_url('/mod/opencast/view.php', ['id' => $moduleid]);
                $text = get_string('addactivity_viewbuttontitle', 'block_opencast');
            } else if (has_capability('block/opencast:addactivity', $coursecontext)) {
                $url = new moodle_url('/blocks/opencast/addactivity.php',
                    ['ocinstanceid' => $ocinstanceid, 'courseid' => $courseid, 'seriesid' => $seriesid]);
                $text = get_string('addactivity_addbuttontitle', 'block_opencast');
            }
            $activitybutton = $this->single_button($url, $text, 'get');
        }

        if (ltimodulemanager::is_enabled_and_working_for_series($ocinstanceid) == true) {
            // Fetch existing LTI series module for this series.
            $moduleid = ltimodulemanager::get_module_for_series($ocinstanceid, $courseid, $seriesid);

            if ($moduleid) {
                $url = new moodle_url('/mod/lti/view.php', ['id' => $moduleid]);
                $text = get_string('addlti_viewbuttontitle', 'block_opencast');
            } else if (has_capability('block/opencast:addlti', $coursecontext)) {
                $url = new moodle_url('/blocks/opencast/addlti.php',
                    ['ocinstanceid' => $ocinstanceid, 'courseid' => $courseid, 'seriesid' => $seriesid]);
                $text = get_string('addlti_addbuttontitle', 'block_opencast');
            }
            $ltibutton = $this->single_button($url, $text, 'get');
        }

        return html_writer::tag('p', $activitybutton) . html_writer::tag('p', $ltibutton);
    }

    /**
     * Create the table of videos.
     * @param string $id
     * @param string[] $headers
     * @param string[] $columns
     * @param string $baseurl
     * @return \block_opencast\local\flexible_table
     */
    public function create_videos_tables($id, $headers, $columns, $baseurl) {
        $table = new block_opencast\local\flexible_table($id);
        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('cellpadding', '3');
        $table->set_attribute('class', 'generaltable opencast-videos-table');
        $table->set_attribute('id', $id);
        $table->headers = $headers;
        $table->define_columns($columns);
        $table->define_baseurl($baseurl);

        $table->no_sorting('action');
        $table->no_sorting('provide');
        $table->no_sorting('provide-activity');
        $table->no_sorting('published');
        $table->no_sorting('visibility'); // This column cannot be sortable because it does not mean anything to Opencast!
        $table->no_sorting('select');
        $table->sortable(true, 'start_date', SORT_DESC);

        $table->column_style('selectall', 'max-width', '40px');
        $table->column_style('start_date', 'min-width', '125px');
        $table->column_style('visibility', 'min-width', '120px');
        $table->column_style('workflow_state', 'min-width', '120px');
        $table->column_style('action', 'min-width', '100px');

        $columnclasses = [
            'selectall' => ['oc-col-select'],
            'workflow_state' => ['oc-col-wfstatus'],
            'visibility' => ['oc-col-visibility'],
        ];

        foreach ($columns as $column) {
            $classes = isset($columnclasses[$column]) ? $columnclasses[$column] : [];
            if ($table->is_sortable($column)) {
                $classes[] = 'oc-sortable-alignment';
            }
            if (!empty($classes)) {
                $table->column_class($column, implode(' ', $classes));
            }
        }

        $table->pageable(true);
        $table->is_downloadable(false);

        $table->set_control_variables(
            [
                TABLE_VAR_SORT => 'tsort',
                TABLE_VAR_PAGE => 'page',
            ]
        );

        $table->setup();
        return $table;

    }

    /**
     * Create the table of all series belonging to a user.
     * @param string $id
     * @param string[] $headers
     * @param string[] $columns
     * @param string $baseurl
     * @return \block_opencast\local\flexible_table
     */
    public function create_series_courses_tables($id, $headers, $columns, $baseurl) {
        $table = new block_opencast\local\flexible_table($id);
        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('cellpadding', '3');
        $table->set_attribute('class', 'generaltable');
        $table->set_attribute('id', $id);
        $table->headers = $headers;
        $table->define_columns($columns);
        $table->define_baseurl($baseurl);

        $table->no_sorting('owner');
        $table->no_sorting('linked');
        $table->no_sorting('activities');
        $table->no_sorting('videos');
        $table->sortable(true, 'series', SORT_DESC);

        $table->pageable(true);
        $table->is_downloadable(false);

        $table->set_control_variables(
            [
                TABLE_VAR_SORT => 'tsort',
                TABLE_VAR_PAGE => 'page',
            ]
        );

        $table->setup();
        return $table;
    }

    /**
     * Create the table of videos and where they are used.
     * @param string $id
     * @param string[] $headers
     * @param string[] $columns
     * @param string $baseurl
     * @return \block_opencast\local\flexible_table
     */
    public function create_overview_videos_table($id, $headers, $columns, $baseurl) {
        $table = new block_opencast\local\flexible_table($id);
        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('cellpadding', '3');
        $table->set_attribute('class', 'generaltable');
        $table->set_attribute('id', $id);
        $table->headers = $headers;
        $table->define_columns($columns);
        $table->define_baseurl($baseurl);

        $table->no_sorting('owner');
        $table->no_sorting('linked');
        $table->no_sorting('activities');
        $table->no_sorting('select');
        $table->no_sorting('action');
        $table->sortable(true, 'videos', SORT_DESC);

        $table->pageable(true);
        $table->is_downloadable(false);

        $table->set_control_variables(
            [
                TABLE_VAR_SORT => 'tsort',
                TABLE_VAR_PAGE => 'page',
            ]
        );

        $table->setup();

        return $table;
    }

    /**
     * Creates the rows for the video overview table.
     * @param array $videos
     * @param object $apibridge
     * @param int $ocinstanceid
     * @param bool $activityinstalled
     * @param bool $showchangeownerlink
     * @param bool $isownerverified
     * @param bool $isseriesowner
     * @param bool $hasaddvideopermissions
     * @param bool $hasdownloadpermission
     * @param bool $hasdeletepermission
     * @param string $redirectpage
     * @param bool $hasaccesspermission
     * @param ?massaction_helper $massaction
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function create_overview_videos_rows($videos, $apibridge, $ocinstanceid, $activityinstalled,
                                                $showchangeownerlink, $isownerverified = false, $isseriesowner = false,
                                                $hasaddvideopermissions = false, $hasdownloadpermission = false,
                                                $hasdeletepermission = false,
                                                $redirectpage = 'overviewvideos', $hasaccesspermission = false,
                                                $massaction = null) {
        global $USER, $SITE, $DB;
        $rows = [];

        foreach ($videos as $video) {

            // This flag here works in false case, because here in overview page we only offer update and delete mass action.
            // We only offer them if the single action has been offered too.
            $isselectable = false;

            $activitylinks = [];
            if ($activityinstalled) {
                $activitylinks = $DB->get_records('opencast', ['ocinstanceid' => $ocinstanceid,
                    'opencastid' => $video->identifier, 'type' => opencasttype::EPISODE, ]);
            }

            $row = [];

            if ($isownerverified || $apibridge->is_owner($video->acl, $USER->id, $SITE->id) ||
                ($isseriesowner && !$apibridge->has_owner($video->acl))) {
                if ($showchangeownerlink) {
                    $row[] = html_writer::link(new moodle_url('/blocks/opencast/changeowner.php',
                        ['ocinstanceid' => $ocinstanceid, 'identifier' => $video->identifier, 'isseries' => false]),
                        $this->output->pix_icon('i/user', get_string('changeowner', 'block_opencast')));
                } else {
                    $row[] = $this->output->pix_icon('i/user', get_string('changeowner', 'block_opencast'));
                }
            } else {
                $row[] = '';
            }

            $row[] = $video->title;
            $courses = [];
            $courseswoblocklink = [];

            foreach ($activitylinks as $accourse) {
                try {
                    // Get activity.
                    $moduleid = activitymodulemanager::get_module_for_episode($accourse->course,
                        $video->identifier, $ocinstanceid);

                    if (seriesmapping::get_record(['ocinstanceid' => $ocinstanceid,
                        'series' => $video->is_part_of, 'courseid' => $accourse->course, ])) {
                        $courses[] = html_writer::link(new moodle_url('/mod/opencast/view.php', ['id' => $moduleid]),
                            get_course($accourse->course)->fullname, ['target' => '_blank']);
                    } else {
                        $courseswoblocklink[] = html_writer::link(new moodle_url('/mod/opencast/view.php',
                            ['id' => $moduleid]),
                            get_course($accourse->course)->fullname, ['target' => '_blank']);
                    }

                } catch (dml_missing_record_exception $ex) {
                    continue;
                }
            }

            $row[] = join('<br>', $courses);
            $row[] = join('<br>', $courseswoblocklink);

            // Actions column.
            $actions = '';
            if ($hasaddvideopermissions) {
                $updatemetadata = $apibridge->can_update_event_metadata($video, $SITE->id, false);
                if ($updatemetadata) {
                    $isselectable = true;
                }
                $actions .= $this->render_edit_functions($ocinstanceid, $SITE->id, $video->identifier, $updatemetadata,
                    false, null, false, false, false, 'overview', $video->is_part_of);
            }

            if ($hasdownloadpermission && $apibridge->can_show_download_button($video, $SITE->id, false)) {
                $actions .= $this->render_download_event_icon($ocinstanceid, $SITE->id, $video);
            }

            if ($hasaccesspermission && $apibridge->can_show_directaccess_link($video, $SITE->id, false)) {
                $actions .= $this->render_direct_link_event_icon($ocinstanceid, $SITE->id, $video);
            }

            if ($hasdeletepermission && isset($video->processing_state) &&
                ($video->processing_state !== 'RUNNING' && $video->processing_state !== 'PAUSED')) {
                $isselectable = true;
                $url = new moodle_url('/blocks/opencast/deleteevent.php',
                    ['identifier' => $video->identifier, 'courseid' => $SITE->id, 'ocinstanceid' => $ocinstanceid,
                        'series' => $video->is_part_of, 'redirectpage' => $redirectpage, ]);
                $text = get_string('deleteevent', 'block_opencast');
                $icon = $this->output->pix_icon('t/delete', $text);
                $actions .= html_writer::link($url, $icon, ['aria-label' => $text]);
            }

            $row[] = $actions;

            if (!is_null($massaction)) {
                $selectcheckbox = $massaction->render_item_checkbox($video, $isselectable);
                if (!empty($selectcheckbox)) {
                    array_unshift($row, $selectcheckbox);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Render the whole block content.
     *
     * @param int $courseid
     * @param object $videodata data as a result from api query against opencast.
     * @param int $ocinstance Opencast instance id.
     * @param bool $rendername
     */
    public function render_block_content($courseid, $videodata, $ocinstance, $rendername) {
        global $USER, $SITE;
        $html = '';

        $coursecontext = context_course::instance($courseid);

        if ($rendername) {
            $html .= $this->output->heading($ocinstance->name);
        }

        if (has_capability('block/opencast:addvideo', $coursecontext) && $SITE->id != $courseid) {
            $addvideourl = new moodle_url('/blocks/opencast/addvideo.php',
                ['courseid' => $courseid, 'ocinstanceid' => $ocinstance->id]);
            $addvideobutton = $this->output->single_button($addvideourl, get_string('addvideo', 'block_opencast'), 'get');
            $html .= html_writer::div($addvideobutton, 'opencast-addvideo-wrap overview');

            // Show "Add videos (batch)" button.
            if (get_config('block_opencast', 'batchuploadenabled_' . $ocinstance->id)) {
                $batchuploadurl = new moodle_url('/blocks/opencast/batchupload.php',
                    ['courseid' => $courseid, 'ocinstanceid' => $ocinstance->id]);
                $batchuploadbutton = $this->output->single_button($batchuploadurl,
                    get_string('batchupload', 'block_opencast'), 'get');
                $html .= html_writer::div($batchuploadbutton, 'opencast-batchupload-wrap overview');
            }

            if (get_config('block_opencast', 'enable_opencast_studio_link_' . $ocinstance->id)) {
                // Initialize the link target to open in the same tab.
                $target = '_self';
                // Check for the admin config to set the link target.
                if (get_config('block_opencast', 'open_studio_in_new_tab_' . $ocinstance->id)) {
                    $target = '_blank';
                }
                // If LTI credentials are given, use LTI. If not, directly forward to Opencast studio.
                $apibridge = apibridge::get_instance($ocinstance->id);
                if (empty($apibridge->get_lti_consumerkey())) {
                    if (empty(get_config('block_opencast', 'opencast_studio_baseurl_' . $ocinstance->id))) {
                        $endpoint = settings_api::get_apiurl($ocinstance->id);
                    } else {
                        $endpoint = get_config('block_opencast', 'opencast_studio_baseurl_' . $ocinstance->id);
                    }

                    if (strpos($endpoint, 'http') !== 0) {
                        $endpoint = 'http://' . $endpoint;
                    }
                    $seriesid = $apibridge->get_stored_seriesid($courseid, true, $USER->id);
                    $studiourlpath = $apibridge->generate_studio_url_path($courseid, $seriesid);
                    $url = $endpoint . $studiourlpath;
                    $recordvideobutton = $this->output->action_link($url, get_string('recordvideo', 'block_opencast'),
                        null, ['class' => 'btn btn-secondary', 'target' => $target]);
                    $html .= html_writer::div($recordvideobutton, 'opencast-recordvideo-wrap overview');
                } else {
                    $recordvideo = new moodle_url('/blocks/opencast/recordvideo.php',
                        ['courseid' => $courseid, 'ocinstanceid' => $ocinstance->id]);
                    $recordvideobutton = $this->output->action_link($recordvideo, get_string('recordvideo', 'block_opencast'),
                        null, ['class' => 'btn btn-secondary', 'target' => $target]);
                    $html .= html_writer::div($recordvideobutton, 'opencast-recordvideo-wrap overview');
                }
            }
        }

        if ($videodata->error) {
            $html .= html_writer::div(get_string('errorgetblockvideos', 'block_opencast', $videodata->error), 'opencast-bc-wrap');

            return $html;
        }

        if ($videodata->count == 0) {

            $html .= html_writer::div(get_string('novideosavailable', 'block_opencast'), 'opencast-bc-wrap');
        } else {

            // Videos available.
            $listitems = '';
            foreach ($videodata->videos as $video) {
                $icon = $this->render_processing_state_icon($video->processing_state);
                $listitems .= html_writer::tag('li', $icon . $video->title, ['class' => 'opencast-vlist-item']);
            }

            $html .= html_writer::tag('ul', $listitems, ['class' => 'opencast-vlist']);
        }

        $moretext = get_string('gotooverview', 'block_opencast');
        if ($videodata->more) {
            $moretext = get_string('morevideos', 'block_opencast');
        }
        $url = new moodle_url('/blocks/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstance->id]);

        // In admin page, we redirect to the series overview page to manage series from there.
        if ($SITE->id == $courseid) {
            $url = new moodle_url('/blocks/opencast/overview.php', ['ocinstanceid' => $ocinstance->id]);
        }
        $link = html_writer::link($url, $moretext);
        $html .= html_writer::div($link, 'opencast-more-wrap');

        return $html;
    }

    /**
     * Render the opencast publication status.
     *
     * @param array $publicationstatus
     *
     * @return string
     */
    public function render_publication_status($publicationstatus) {

        if (empty($publicationstatus)) {
            return get_string('notpublished', 'block_opencast');
        }

        return implode(', ', $publicationstatus);
    }

    /**
     * Render the opencast processing status.
     *
     * @param string $statuscode
     * @param int $countfailed
     *
     * @return string
     */
    public function render_status($statuscode, $countfailed = 0) {
        $statusstring = '';
        // The status code less than 200 is assigned for api upload.
        if (intval($statuscode) < 200) {
            // Get understandable status string from normal upload process.
            $statusstring = upload_helper::get_status_string($statuscode);
        }

        // The status code greater than 200 is assigned for ingest upload.
        if (intval($statuscode) >= 200) {
            $statusstring = ingest_uploader::get_status_string($statuscode);
        }

        // It the statusstring is still empty, we return unknown.
        if (empty($statusstring)) {
            $statusstring = get_string('mstateunknown', 'block_opencast');
        }

        // If needed, add the number of failed uploads.
        if ($countfailed > 1) {
            $statusstring .= ' (' . get_string('failedtransferattempts', 'block_opencast', $countfailed) . ')';
        }

        // Return string.
        return $statusstring;
    }

    /**
     * Render the tabel of upload jobs.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param array $uploadjobs array of uploadjob objects
     * @param bool $showactionbuttons A flag indicating whether to show action buttons.
     * @param string $redirectpage
     * @param string $seriesid
     * @return string
     */
    public function render_upload_jobs($ocinstanceid, $uploadjobs, $showactionbuttons = true,
                                       $redirectpage = null, $seriesid = null) {
        // Set if visibility change is enabled.
        $canchangescheduledvisibility = false;
        if (get_config('block_opencast', 'aclcontrolafter_' . $ocinstanceid) &&
            !empty(get_config('block_opencast', 'workflow_roles_' . $ocinstanceid))) {
            $canchangescheduledvisibility = true;
        }
        $table = new html_table();
        $table->head = [
            get_string('hstart_date', 'block_opencast'),
            get_string('title', 'block_opencast'),
            get_string('series', 'block_opencast'),
            get_string('presenterfile', 'block_opencast'),
            get_string('presentationfile', 'block_opencast'),
            get_string('status'),
            get_string('createdby', 'block_opencast'), ];
        if ($canchangescheduledvisibility) {
            $table->head[] = get_string('hscheduledvisibility', 'block_opencast');
        }
        if ($showactionbuttons) {
            $table->head[] = '';
        }

        foreach ($uploadjobs as $uploadjob) {

            $uploadjob->metadata ? $metadata = json_decode($uploadjob->metadata) : $metadata = '';
            $title = '';
            $series = '';
            $startdatetime = null;
            if ($metadata) {
                $startdate = '';
                $starttime = '';
                foreach ($metadata as $ms) {
                    if ($ms->id == 'title') {
                        $title = $ms->value;
                    } else if ($ms->id == 'startDate') {
                        $startdate = $ms->value;
                    } else if ($ms->id == 'startTime') {
                        $starttime = $ms->value;
                    } else if ($ms->id == 'isPartOf') {
                        $apibridge = apibridge::get_instance($ocinstanceid);
                        $ocseries = $apibridge->get_series_by_identifier($ms->value);
                        if ($ocseries) {
                            $series = $ocseries->title;
                        }
                    }
                }

                if ($startdate && $starttime) {
                    $startdatetime = date_create_from_format('Y-m-d H:i:s\Z', $startdate . ' ' . $starttime,
                        new DateTimeZone("UTC"));
                }
            }

            $row = [];
            if ($startdatetime) {
                $row[] = userdate($startdatetime->getTimestamp(), get_string('strftimedatetime', 'langconfig'));
            } else {
                $row[] = '';
            }

            $row[] = $title;
            $row[] = $series;

            if ($uploadjob->presenter_filename) {
                if ($uploadjob->presenter_filesize) {
                    $row[] = $uploadjob->presenter_filename . ' (' . display_size($uploadjob->presenter_filesize) . ')';
                } else {
                    $row[] = $uploadjob->presenter_filename;
                }
            } else if (property_exists($uploadjob, 'presenter_chunkupload_filename')) {
                if ($uploadjob->presenter_chunkupload_filesize) {
                    $row[] = $uploadjob->presenter_chunkupload_filename .
                        ' (' . display_size($uploadjob->presenter_chunkupload_filesize) . ')';
                } else {
                    $row[] = $uploadjob->presenter_chunkupload_filename;
                }
            } else {
                $row[] = '&mdash;';
            }
            if ($uploadjob->presentation_filename) {
                if ($uploadjob->presentation_filesize) {
                    $row[] = $uploadjob->presentation_filename . ' (' . display_size($uploadjob->presentation_filesize) . ')';
                } else {
                    $row[] = $uploadjob->presentation_filename;
                }
            } else if (property_exists($uploadjob, 'presentation_chunkupload_filename')) {
                if ($uploadjob->presentation_chunkupload_filesize) {
                    $row[] = $uploadjob->presentation_chunkupload_filename .
                        ' (' . display_size($uploadjob->presentation_chunkupload_filesize) . ')';
                } else {
                    $row[] = $uploadjob->presentation_chunkupload_filename;
                }
            } else {
                $row[] = '&mdash;';
            }
            $status = $this->render_status($uploadjob->status, $uploadjob->countfailed);
            // Add live update flag item (hidden input) to the upload status column, if it is enabled.
            if (boolval(get_config('block_opencast', 'liveupdateenabled_' . $ocinstanceid)) &&
                $uploadjob->status != upload_helper::STATUS_ARCHIVED_FAILED_UPLOAD) {
                $status .= liveupdate_helper::get_liveupdate_uploading_hidden_input($uploadjob->id, $title);
            }
            $row[] = $status;
            $row[] = fullname($uploadjob);
            if ($canchangescheduledvisibility) {
                $row[] = $this->render_scheduled_visibility_icon($uploadjob);
            }
            if ($showactionbuttons) {
                $actionbuttonshtml = '';
                $coursecontext = context_course::instance($uploadjob->courseid);
                if (has_capability('block/opencast:addvideo', $coursecontext)) {
                    // Rendering a button to put the upload job back into the queue aka unarchive.
                    if ($uploadjob->status == upload_helper::STATUS_ARCHIVED_FAILED_UPLOAD) {
                        $actionbuttonshtml .= $this->render_unarchive_uploadjob_icon(
                            $uploadjob->ocinstanceid,
                            $uploadjob->courseid,
                            $uploadjob->id,
                        );
                    }
                    // The one who is allowed to add the video is also allowed to delete the video before it is uploaded.
                    if ($uploadjob->status == upload_helper::STATUS_READY_TO_UPLOAD ||
                        $uploadjob->status == upload_helper::STATUS_ARCHIVED_FAILED_UPLOAD) {
                        $actionbuttonshtml .= $this->render_delete_draft_icon(
                            $uploadjob->ocinstanceid,
                            $uploadjob->courseid,
                            $uploadjob->id,
                            $redirectpage,
                            $seriesid
                        );
                    }
                }

                $row[] = $actionbuttonshtml;
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Returns the icon string or a dash - to be presented in the upload job table.
     *
     * @param object $uploadjob the upload job object
     * @return string the icon string or a dash - to be presented in the upload job table
     */
    private function render_scheduled_visibility_icon($uploadjob) {
        $scheduledvisibility = visibility_helper::get_uploadjob_scheduled_visibility($uploadjob->id);
        $coursecontext = context_course::instance($uploadjob->courseid);
        if (!empty($scheduledvisibility) && has_capability('block/opencast:addvideo', $coursecontext)) {
            $url = new moodle_url('/blocks/opencast/changescheduledvisibility.php',
                ['uploadjobid' => $uploadjob->id, 'courseid' => $uploadjob->courseid,
                    'ocinstanceid' => $uploadjob->ocinstanceid, ]);
            $text = get_string('scheduledvisibilityicontitle', 'block_opencast');
            $icon = $this->output->pix_icon('i/scheduled', $text);
            return html_writer::link($url, $icon);
        }
        return '&mdash;';
    }

    /**
     * Render the link to delete a group assignment.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $videoidentifier
     */
    public function render_delete_acl_group_assignment_icon($ocinstanceid, $courseid, $videoidentifier) {

        $url = new moodle_url('/blocks/opencast/deleteaclgroup.php',
            ['identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
        $text = get_string('deleteaclgroup', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return html_writer::link($url, $icon);
    }

    /**
     * Render the link to change the visibility of a video.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $videoidentifier
     * @param int $visible
     */
    public function render_change_visibility_icon($ocinstanceid, $courseid, $videoidentifier, $visible) {
        global $USER;
        $url = new moodle_url('/blocks/opencast/changevisibility.php',
            ['identifier' => $videoidentifier, 'courseid' => $courseid,
                'visibility' => $visible, 'sesskey' => $USER->sesskey, 'ocinstanceid' => $ocinstanceid, ]);

        switch ($visible) {
            case self::VISIBLE:
                $text = get_string('changevisibility_visible', 'block_opencast');
                $icon = $this->output->pix_icon('t/hide', $text);
                break;
            case self::MIXED_VISIBILITY:
                $text = get_string('changevisibility_mixed', 'block_opencast');
                $icon = $this->output->pix_icon('i/warning', $text);
                break;
            case self::GROUP:
                $text = get_string('changevisibility_group', 'block_opencast');
                $icon = $this->output->pix_icon('t/groups', $text);
                break;
            case self::HIDDEN:
                $text = get_string('changevisibility_hidden', 'block_opencast');
                $icon = $this->output->pix_icon('t/show', $text);
                break;
        }

        return html_writer::link($url, $icon, ['aria-label' => $text]);
    }

    /**
     * Render the information about the video before deleting the assignment of the event
     * to the course series.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param object $video
     * @return string
     */
    public function render_video_info($ocinstanceid, $courseid, $video) {

        if (!$video) {
            return get_string('videonotfound', 'block_opencast');
        }

        $html = get_string('deletegroupacldesc', 'block_opencast');

        $table = new html_table();
        $table->head = [
            get_string('hstart_date', 'block_opencast'),
            get_string('htitle', 'block_opencast'),
            get_string('hpublished', 'block_opencast'),
            get_string('hworkflow_state', 'block_opencast'),
        ];

        $row = [];

        $row[] = $this->render_created($video->start);
        $row[] = $video->title;
        $row[] = $this->render_publication_status($video->publication_status);
        $row[] = $this->render_processing_state_icon($video->processing_state);

        $table->data[] = $row;

        $html .= html_writer::table($table);

        $label = get_string('dodeleteaclgroup', 'block_opencast');
        $params = [
            'identifier' => $video->identifier,
            'courseid' => $courseid,
            'action' => 'delete',
            'ocinstanceid' => $ocinstanceid,
        ];
        $url = new moodle_url('/blocks/opencast/deleteaclgroup.php', $params);
        $html .= $this->output->single_button($url, $label);

        return $html;
    }

    /**
     * Render the link to delete a group assignment.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $videoidentifier
     */
    public function render_delete_event_icon($ocinstanceid, $courseid, $videoidentifier) {

        $url = new moodle_url('/blocks/opencast/deleteevent.php',
            ['identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
        $text = get_string('deleteevent', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return html_writer::link($url, $icon, ['aria-label' => $text]);
    }

    /**
     * Render the icon to add an LTI episode module.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return string
     */
    public function render_add_lti_episode_icon($ocinstanceid, $courseid, $episodeuuid) {
        $url = new moodle_url('/blocks/opencast/addltiepisode.php',
            ['episodeuuid' => $episodeuuid, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
        $text = get_string('addltiepisode_addicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('share', $text, 'block_opencast');

        return html_writer::link($url, $icon);
    }

    /**
     * Render the icon to view an LTI episode module.
     *
     * @param int $moduleid
     *
     * @return string
     */
    public function render_view_lti_episode_icon($moduleid) {

        $url = new moodle_url('/mod/lti/view.php', ['id' => $moduleid]);
        $text = get_string('addltiepisode_viewicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('play', $text, 'block_opencast');

        return html_writer::link($url, $icon);
    }

    /**
     * Render the icon to add an Opencast Activity episode module.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return string
     */
    public function render_add_activity_episode_icon($ocinstanceid, $courseid, $episodeuuid) {
        $url = new moodle_url('/blocks/opencast/addactivityepisode.php',
            ['episodeuuid' => $episodeuuid, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
        $text = get_string('addactivityepisode_addicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('share', $text, 'block_opencast');

        return html_writer::link($url, $icon);
    }

    /**
     * Render the icon to view an Opencast Activity episode module.
     *
     * @param int $moduleid
     *
     * @return string
     */
    public function render_view_activity_episode_icon($moduleid) {

        $url = new moodle_url('/mod/opencast/view.php', ['id' => $moduleid]);
        $text = get_string('addactivityepisode_viewicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('play', $text, 'block_opencast');

        return html_writer::link($url, $icon);
    }

    /**
     * Render the link to delete a draft file.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $videoidentifier
     * @param string $redirectpage
     * @param string $series
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function render_delete_draft_icon($ocinstanceid, $courseid, $videoidentifier, $redirectpage = null, $series = null) {

        $url = new moodle_url('/blocks/opencast/deletedraft.php',
            ['identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
                'redirectpage' => $redirectpage, 'series' => $series, ]);
        $text = get_string('dodeleteevent', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return html_writer::link($url, $icon);
    }

    /**
     * Render the link that unarchives the upload job.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param string $uploadjobid
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function render_unarchive_uploadjob_icon($ocinstanceid, $courseid, $uploadjobid) {
        $text = get_string('unarchiveuploadjobbtntitle', 'block_opencast');

        $icon = $this->output->pix_icon(
            'i/reload',
            $text,
            'moodle'
        );

        return html_writer::link(new moodle_url('#'), $icon, ['class' => 'unarchive-uploadjob', 'data-id' => $uploadjobid]);
    }

    /**
     * Render menu of edit options for a video.
     * @param int $ocinstanceid
     * @param int $courseid
     * @param string $videoidentifier
     * @param bool $updatemetadata
     * @param bool $startworkflows
     * @param stdClass $coursecontext
     * @param bool $useeditor
     * @param bool $canchangeowner
     * @param bool $canmanagetranscriptions
     * @param string $redirectpage
     * @param string $series
     * @return bool|string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function render_edit_functions($ocinstanceid, $courseid, $videoidentifier, $updatemetadata,
                                          $startworkflows, $coursecontext, $useeditor, $canchangeowner,
                                          $canmanagetranscriptions, $redirectpage = null, $series = null) {
        global $CFG;

        // Get the action menu options.
        $actionmenu = new action_menu();
        if ($CFG->branch >= 400) {
            $actionmenu->set_menu_left();
        } else {
            $actionmenu->set_alignment(action_menu::TL, action_menu::BL);
        }

        // This is a fix for MDL-77375, following PR #339.
        if (method_exists($actionmenu, 'set_boundary')) {
            $actionmenu->set_boundary('window');
        }

        $actionmenu->prioritise = true;
        $actionmenu->attributes['class'] .= ' inline-action-menu';

        if ($updatemetadata) {
            // Update metadata event.
            $actionmenu->add(new action_menu_link_secondary(
                new moodle_url('/blocks/opencast/updatemetadata.php',
                    ['video_identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
                        'redirectpage' => $redirectpage, 'series' => $series, ]),
                new pix_icon('t/editstring', get_string('updatemetadata_short', 'block_opencast')),
                get_string('updatemetadata_short', 'block_opencast')
            ));
        }

        if ($canmanagetranscriptions) {
            // Event's transcriptions menu.
            $actionmenu->add(new action_menu_link_secondary(
                new moodle_url('/blocks/opencast/managetranscriptions.php',
                    ['video_identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]),
                new pix_icon('caption', get_string('managetranscriptions', 'block_opencast'), 'block_opencast'),
                get_string('managetranscriptions', 'block_opencast')
            ));
        }

        if ($useeditor) {
            $actionmenu->add(new action_menu_link_secondary(
                new moodle_url('/blocks/opencast/videoeditor.php',
                    ['video_identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]),
                new pix_icon('e/cut', get_string('ocstateneedscutting', 'block_opencast')),
                get_string('videoeditor_short', 'block_opencast'),
                ['target' => '_blank']
            ));
        }

        if ($startworkflows && has_capability('block/opencast:startworkflow', $coursecontext)) {
            $actionmenu->add(new action_menu_link_secondary(
                new moodle_url('#'),
                new pix_icon('t/collapsed', get_string('startworkflow', 'block_opencast')),
                get_string('startworkflow', 'block_opencast'),
                ['class' => 'start-workflow', 'data-id' => $videoidentifier]
            ));
        }

        if ($canchangeowner) {
            $actionmenu->add(new action_menu_link_secondary(
                new moodle_url('/blocks/opencast/changeowner.php',
                    ['identifier' => $videoidentifier, 'ocinstanceid' => $ocinstanceid, 'courseid' => $courseid,
                        'isseries' => false, ]),
                new pix_icon('t/user', get_string('changeowner', 'block_opencast')),
                get_string('changeowner', 'block_opencast')
            ));
        }

        return $this->render($actionmenu);
    }

    /**
     * Render the information about the video before finally delete it.
     *
     * @param int $ocinstanceid Opencast instance id.
     * @param int $courseid
     * @param object $video
     * @param string $redirectpage
     * @param string $series
     * @return string
     */
    public function render_video_deletion_info($ocinstanceid, $courseid, $video, $redirectpage = null, $series = null) {

        if (!$video) {
            return get_string('videonotfound', 'block_opencast');
        }

        $html = $this->output->notification(get_string('deleteeventdesc', 'block_opencast'), 'error');

        $table = new html_table();
        $table->head = [];
        $table->head[] = get_string('hstart_date', 'block_opencast');
        $table->head[] = get_string('htitle', 'block_opencast');
        if (get_config('block_opencast', 'showpublicationchannels_' . $ocinstanceid)) {
            $table->head[] = get_string('hpublished', 'block_opencast');
        }
        $table->head[] = get_string('hworkflow_state', 'block_opencast');

        $row = [];

        $row[] = $this->render_created($video->start);
        $row[] = $video->title;
        if (get_config('block_opencast', 'showpublicationchannels_' . $ocinstanceid)) {
            $row[] = $this->render_publication_status($video->publication_status);
        }
        $row[] = $this->render_processing_state_icon($video->processing_state);

        $table->data[] = $row;

        $html .= html_writer::table($table);

        $label = get_string('dodeleteevent', 'block_opencast');
        $params = [
            'identifier' => $video->identifier,
            'courseid' => $courseid,
            'action' => 'delete', 'ocinstanceid' => $ocinstanceid,
            'redirectpage' => $redirectpage,
            'series' => $series,
        ];
        $url = new moodle_url('/blocks/opencast/deleteevent.php', $params);
        $html .= $this->output->single_button($url, $label);

        return $html;
    }

    /**
     * Render a unordered list with given items.
     *
     * @param array $items to use as list elements
     * @return string
     */
    public function render_list($items) {

        $o = '';
        if (count($items) == 0) {
            return $o;
        }

        foreach ($items as $item) {
            $o .= html_writer::tag('li', $item);
        }

        return html_writer::tag('ul', $o);
    }

    /**
     * Render link to manage series page.
     * @param int $ocinstanceid
     * @param int $courseid
     * @return string
     * @throws moodle_exception
     */
    public function render_series_settings_actions(int $ocinstanceid, int $courseid): string {
        $context = new stdClass();
        $context->hasanyactions = true;
        $url = new moodle_url('/blocks/opencast/manageseries.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
        $context->manageseriesurl = $url->out();
        return $this->render_from_template('block_opencast/series_settings_actions', $context);
    }

    /**
     * Render link that redirects to manage defaults page.
     * @param int $ocinstanceid
     * @param int $courseid
     * @return string
     * @throws moodle_exception
     */
    public function render_defaults_settings_actions(int $ocinstanceid, int $courseid): string {
        $context = new stdClass();
        $context->hasanyactions = true;
        $url = new moodle_url('/blocks/opencast/managedefaults.php',
            ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid]);
        $context->managedefaultsurl = $url->out();
        return $this->render_from_template('block_opencast/defaults_settings_actions', $context);
    }

    /**
     * Display the lti form.
     *
     * @param string $endpoint
     * @param array $params The prepared variables.
     * @return string
     */
    public function render_lti_form($endpoint, $params) {
        $content = "<form action=\"" . $endpoint .
            "\" name=\"ltiLaunchForm\" id=\"ltiLaunchForm\" method=\"post\" encType=\"application/x-www-form-urlencoded\">\n";

        // Construct html form for the launch parameters.
        foreach ($params as $key => $value) {
            $key = htmlspecialchars($key, ENT_COMPAT);
            $value = htmlspecialchars($value, ENT_COMPAT);
            $content .= "<input type=\"hidden\" name=\"{$key}\"";
            $content .= " value=\"";
            $content .= $value;
            $content .= "\"/>\n";
        }
        $content .= "</form>\n";

        return $content;
    }

    /**
     * Render download icon for a video.
     * @param int $ocinstanceid
     * @param int $courseid
     * @param stdClass $video
     * @return bool|string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_download_event_icon($ocinstanceid, $courseid, $video) {
        global $CFG;

        // Get the action menu options.
        $actionmenu = new action_menu();
        if ($CFG->branch >= 400) {
            $actionmenu->set_menu_left();
        } else {
            $actionmenu->set_alignment(action_menu::TL, action_menu::BL);
        }

        // This is a fix for MDL-77375, following PR #339.
        if (method_exists($actionmenu, 'set_boundary')) {
            $actionmenu->set_boundary('window');
        }

        $actionmenu->prioritise = true;
        $actionmenu->actionicon = new pix_icon('t/down', get_string('downloadvideo', 'block_opencast'));
        $actionmenu->set_menu_trigger(' ');
        $actionmenu->attributes['class'] .= ' download-action-menu';

        foreach ($video->publications as $publication) {
            if ($publication->channel == get_config('block_opencast', 'download_channel_' . $ocinstanceid)) {
                foreach ($publication->media as $media) {
                    $width = property_exists($media, 'width') ? $media->width : 0;
                    $height = property_exists($media, 'height') ? $media->height : 0;
                    $name = ucwords(explode('/', $media->flavor)[0]) . ' (' . $width . 'x' . $height . ')';
                    $actionmenu->add(new action_menu_link_secondary(
                        new moodle_url('/blocks/opencast/downloadvideo.php',
                            ['video_identifier' => $video->identifier, 'courseid' => $courseid,
                                'mediaid' => $media->id, 'ocinstanceid' => $ocinstanceid, ]),
                        null,
                        $name
                    ));
                }
            }
        }

        return $this->render($actionmenu);
    }

    /**
     * Render share icon for a video.
     * @param int $ocinstanceid
     * @param int $courseid
     * @param stdClass $video
     * @return bool|string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_direct_link_event_icon($ocinstanceid, $courseid, $video) {
        global $CFG;

        // Get the action menu options.
        $actionmenu = new action_menu();
        if ($CFG->branch >= 400) {
            $actionmenu->set_menu_left();
        } else {
            $actionmenu->set_alignment(action_menu::TL, action_menu::BL);
        }

        // This is a fix for MDL-77375, following PR #339.
        if (method_exists($actionmenu, 'set_boundary')) {
            $actionmenu->set_boundary('window');
        }

        $actionmenu->prioritise = true;
        $actionmenu->actionicon = new pix_icon('e/anchor', get_string('directaccesstovideo', 'block_opencast'), 'moodle');
        $actionmenu->set_menu_trigger(' ');
        $actionmenu->attributes['class'] .= ' access-action-menu';

        foreach ($video->publications as $publication) {
            if ($publication->channel == get_config('block_opencast', 'direct_access_channel_' . $ocinstanceid)) {
                foreach ($publication->media as $media) {
                    $width = property_exists($media, 'width') ? $media->width : 0;
                    $height = property_exists($media, 'height') ? $media->height : 0;
                    $name = ucwords(explode('/', $media->flavor)[0]) . ' (' . $width . 'x' . $height . ')';
                    $url = new moodle_url('/blocks/opencast/directaccess.php',
                        ['video_identifier' => $video->identifier, 'courseid' => $courseid,
                            'mediaid' => $media->id, 'ocinstanceid' => $ocinstanceid, ]);

                    $accesslink = new action_menu_link_secondary($url,
                        new pix_icon('t/copy', get_string('directaccesscopylink', 'block_opencast')),
                        $name,
                        ['title' => get_string('directaccesscopylink', 'block_opencast')]);
                    $accesslink->attributes['class'] .= ' access-link-copytoclipboard';
                    $actionmenu->add($accesslink);
                }
            }
        }

        return $this->render($actionmenu);
    }

    /**
     * Render report problem icon for a video.
     * @param string $identifier
     * @return string
     * @throws coding_exception
     */
    public function render_report_problem_icon($identifier) {
        $icon = $this->output->pix_icon('t/message', get_string('reportproblem_modal_title', 'block_opencast'));
        return html_writer::link('#', $icon, ['class' => 'report-problem', 'data-id' => $identifier,
            'aria-label' => get_string('reportproblem_modal_title', 'block_opencast'), ]);
    }

    /**
     * Render series table for "manage series" page.
     * @param int $ocinstanceid
     * @param int $courseid
     * @return bool|string
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_manage_series_table($ocinstanceid, $courseid) {
        global $DB;
        $coursecontext = context_course::instance($courseid);
        $countseries = $DB->count_records('tool_opencast_series', ['ocinstanceid' => $ocinstanceid, 'courseid' => $courseid]);

        $context = new stdClass();
        $context->maxseriesreached = $countseries >= get_config('block_opencast', 'maxseries_' . $ocinstanceid);
        $context->cancreateseries = has_capability('block/opencast:createseriesforcourse', $coursecontext);
        $context->canimportseries = has_capability('block/opencast:importseriesintocourse', $coursecontext);

        return $this->render_from_template('block_opencast/series_table', $context);
    }

    /**
     * Renderes a help icon from core template, but with custom text to display.
     *
     * @param string $title The title to be displayed when hovering over the help icon.
     * @param string $content The description text to be displayed when the help icon is clicked.
     * @return string
     */
    public function render_help_icon_with_custom_text($title, $content) {
        $context = new stdClass();
        $context->title = get_string('helpprefix2', '', $title);
        $context->alt = get_string('helpprefix2', '', $title);
        $context->ltr = !right_to_left();

        $context->text = format_text($content);

        return $this->output->render_from_template('core/help_icon', $context);
    }

    /**
     * Render transcription table for "manage transcription" page.
     * @param array $list list of current transcriptions
     * @param string $addnewurl add new transcription url
     * @param boolean $candelete whether to provide delete feature
     * @param boolean $allowdownload whether to redirect download to a new page or not
     * @return bool|string
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function render_manage_transcriptions_table($list = [], $addnewurl = '',
                                                       $candelete = false, $allowdownload = false) {
        $context = new stdClass();
        $context->list = $list;
        $context->listhascontent = !empty($list) ? true : false;
        $context->addnewurl = $addnewurl;
        $context->candelete = $candelete;
        $context->allowdownload = $allowdownload;
        $context->hasactions = ($candelete || $allowdownload);
        return $this->render_from_template('block_opencast/transcriptions_table', $context);
    }

    /**
     * Helper function to validate and close any missing tags in a html string.
     * It is a sanity check and correction to ensure that a html string has all tags closed correctly.
     *
     * @param string $html The html string
     * @return string The html string
     */
    public function close_tags_in_html_string($html) {
        preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
        $openedtags = $result[1];
        preg_match_all('#</([a-z]+)>#iU', $html, $result);
        $closedtags = $result[1];
        $lenopened = count($openedtags);
        if (count($closedtags) == $lenopened) {
            return $html;
        }
        $openedtags = array_reverse($openedtags);
        // Close tags.
        for ($i = 0; $i < $lenopened; $i++) {
            if (!in_array($openedtags[$i], $closedtags)) {
                $html .= '</' . $openedtags[$i] . '>';
            } else {
                unset($closedtags[array_search($openedtags[$i], $closedtags)]);
            }
        }
        return $html;
    }

    /**
     * Returns the transcription list item extracted from either caption media track or caption attachment publication.
     * INFO: This method covers all of the caption formats (Opencast 15 and older).
     *
     * @param object $transcriptionitem the caption publication object.
     * @param int $courseid course id.
     * @param int $ocinstanceid opencast instance id.
     * @param string $identifier event identifier.
     * @param string $domain a flag to determine where that mediapackage subcategory belongs to (attachments or media).
     * @param array $flavors a list of pre-defined transcriptions flavors.
     *
     * @return stdClass the list item object.
     */
    public function prepare_transcription_item_for_the_menu($transcriptionitem, $courseid, $ocinstanceid,
                                                            $identifier, $domain, $flavors) {
        $lang = '';
        $itemtitle = '';
        $flavorsplitted = explode('/', $transcriptionitem->flavor, 2);
        $mainflavor = $flavorsplitted[0];
        if ($mainflavor != attachment_helper::TRANSCRIPTION_FLAVOR_TYPE) {
            return null;
        }
        $subflavor = $flavorsplitted[1];
        // In case we have vtt+{lang}, then it is considered as manual or the old subtitle management of Opencast,
        // which we need to support.
        if (strpos($subflavor, 'vtt+') !== false) {
            $lang = str_replace('vtt+', '', $subflavor);
            $itemtitle = $lang;
            if (array_key_exists($lang, $flavors)) {
                $itemtitle = $flavors[$lang];
            }
            $itemtitle .= ' (' . get_string('transcriptionmanual', 'block_opencast') . ')';
        } else if (in_array($subflavor, attachment_helper::TRANSCRIPTION_SUBFLAVOR_TYPES) && !empty($transcriptionitem->tags)) {
            // In Opencast 15 and above we shift to caption/delivery.
            $$tagdataarr = [];
            foreach ($transcriptionitem->tags as $tag) {
                // The safety checker.
                if (!is_string($tag)) {
                    continue;
                }
                if (strpos($tag, 'lang:') !== false) {
                    $lang = str_replace('lang:', '', $tag);
                    $tagdataarr['lang'] = $lang;
                }
                if (strpos($tag, 'generator-type:') !== false) {
                    $tagdataarr['generatortype'] = str_replace('generator-type:', '', $tag);
                }
                if (strpos($tag, 'generator:') !== false) {
                    $tagdataarr['generator'] = str_replace('generator:', '', $tag);
                }
                if (strpos($tag, 'type:') !== false) {
                    $tagdataarr['type'] = str_replace('type:', '', $tag);
                }
            }
            $itemtitle = $this->prepare_transcription_item_title($tagdataarr);
        }

        $item = new stdClass();

        // Extracting id and type from attributes.
        $item->id = $transcriptionitem->id;
        $item->flavor = $transcriptionitem->flavor;

        $item->title = !empty($itemtitle) ?
                    $itemtitle :
                    get_string('notranscriptionflavor', 'block_opencast', $lang);

        // Preparing delete url.
        $deleteurl = new moodle_url('/blocks/opencast/deletetranscription.php',
            ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid,
                'video_identifier' => $identifier, 'transcription_identifier' => $item->id, ]);
        $item->deleteurl = $deleteurl->out(false);

        // Preparing download url.
        $downloadurl = new moodle_url('/blocks/opencast/downloadtranscription.php',
            ['courseid' => $courseid, 'ocinstanceid' => $ocinstanceid, 'domain' => $domain,
                'video_identifier' => $identifier, 'transcription_identifier' => $item->id, ]);
        $item->downloadurl = $downloadurl->out(false);

        return $item;
    }

    /**
     * Prepares the transcription item title based of the tags data.
     *
     * @param array $tagdataarr the array of extraced data from the tag.
     *
     * @return string the title
     */
    private function prepare_transcription_item_title($tagdataarr) {
        $titlearr = [];
        if (array_key_exists('generator', $tagdataarr)) {
            $generator = ucfirst($tagdataarr['generator']);
            $titlearr[] = $generator;
        }
        if (array_key_exists('lang', $tagdataarr)) {
            $titlearr[] = $tagdataarr['lang'];
        }
        if (array_key_exists('generatortype', $tagdataarr)) {
            $stringkey = $tagdataarr['generatortype'] == 'auto' ? 'transcriptionauto' : 'transcriptionmanual';
            $generatortype = get_string($stringkey, 'block_opencast');
            $titlearr[] = "({$generatortype})";
        }
        if (array_key_exists('type', $tagdataarr)) {
            $type = ucfirst($tagdataarr['type']);
            $titlearr[] = "({$type})";
        }
        return implode(' - ', $titlearr);
    }

    /**
     * Looks through the html string of workflow configuration panel and
     * converts select elements as well as input elements of types (checkbox, text, datetime, radio)
     * into moodle form elements.
     *
     * @param MoodleQuickForm $mform referenced moodle form of upload videos single and batch.
     * @param string $configurationpanelhtml workflow configuration panel html as string.
     * @param array $alloweduploadwfconfigids list of allowed configuration element ids.
     */
    public function render_configuration_panel_form_elements(&$mform, $configurationpanelhtml, $alloweduploadwfconfigids = []) {

        if (empty($configurationpanelhtml)) {
            return;
        }

        // Make sure the html string is valid.
        $html = $this->close_tags_in_html_string($configurationpanelhtml);

        // Initialize the dom and xpath instances.
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->loadHTML($html);
        $xpath = new \DOMXpath($dom);

        // Extracting all inputs and selects in one loop to maintain the sequence and convert them into moodle form elements.
        $elements = $xpath->query('//input | //select');
        $radiotexts = [];
        $defaults = [];
        $configpanelelementmapping = [];
        foreach ($elements as $element) {
            $id = trim($element->getAttribute('id'));
            // If it is decided to provide only allowed configs, we filter them here!
            if (!empty($alloweduploadwfconfigids) && !in_array($id, $alloweduploadwfconfigids)) {
                continue;
            }
            $nodetype = $element->tagName;
            $name = trim($element->getAttribute('name'));
            $required = $element->hasAttribute('required') ?? false;
            $labeltext = $this->get_dom_label_text($xpath, $dom, $id);
            $title = $labeltext ?? $id;
            $default = null;
            $moodleid = $id;
            $mappingtype = 'text';

            // Conver input to moodle form element.
            if ($nodetype === 'input') {
                $type = trim($element->getAttribute('type'));
                $value = trim($element->getAttribute('value')) ?? null;

                // Support for radio elements.
                if ($type === 'radio') {
                    $moodleid = $name;
                    $parenttext = '';
                    if (!array_key_exists($moodleid, $radiotexts)) {
                        $parenttext = $this->get_parent_label_text($xpath, $dom, $id);
                        $radiotexts[$moodleid] = $parenttext;
                    }
                    $mform->addElement('radio', $moodleid, $parenttext, $title, $value);
                    $ischecked = $element->hasAttribute('checked');
                    if (!empty($ischecked)) {
                        $default = $value;
                    }
                }

                // Support for number and text elements.
                if ($type === 'number' || $type === 'text') {
                    $mform->addElement('text', $moodleid, $title);
                    $elementtype = $type === 'number' ? PARAM_INT : PARAM_TEXT;
                    $mform->setType($moodleid, $elementtype);
                    if (!is_null($value)) {
                        $default = $value;
                    }
                }

                // Support for date and datetime elements.
                if (substr($type, 0, 4) === 'date') {
                    $mappingtype = 'date';
                    $moodletype = substr($type, 0, 8) === 'datetime' ? 'date_time_selector' : 'date_selector';
                    $mform->addElement($moodletype, $moodleid, $title);
                    if (!is_null($value)) {
                        $default = $value;
                    }
                }

                // Support for checkbox elements.
                if ($type === 'checkbox') {
                    $mappingtype = 'boolean';
                    $mform->addElement('checkbox', $moodleid, $title);
                    if (!is_null($value)) {
                        $default = $value;
                    }
                }

            } else if ($nodetype === 'select') { // Conver select to moodle form element.
                // Extract options.
                $options = [];
                $optionnodes = $element->childNodes;
                $selected = null;
                foreach ($optionnodes as $node) {
                    if ($node->tagName === 'option') {
                        $optionvalue = $node->getAttribute('value');
                        $options[$optionvalue] = $node->textContent;
                        if ($node->hasAttribute('selected')) {
                            $selected = $optionvalue;
                        }
                    }
                }

                $mform->addElement('select', $moodleid, $title, $options);
                if (!is_null($selected)) {
                    $default = $selected;
                }
            }

            // Add required rule.
            if ($required) {
                $mform->addRule($moodleid, get_string('required'), 'required');
            }

            // Add default value.
            if (!is_null($default)) {
                $defaults[$moodleid] = $default;
            }

            // We need to keep track of moodleids and mapping types for the elements added by configuration panel.
            if (!array_key_exists($moodleid, $configpanelelementmapping)) {
                $configpanelelementmapping[$moodleid] = $mappingtype;
            }
        }

        // Apply defaults.
        if (!empty($defaults)) {
            foreach ($defaults as $moodleid => $value) {
                $mform->setDefault($moodleid, $value);
            }
        }

        if (!empty($configpanelelementmapping)) {
            $mform->addElement('hidden',
                workflowconfiguration_helper::MAPPING_INPUT_HIDDEN_ID,
                json_encode($configpanelelementmapping)
            );
            $mform->setType(workflowconfiguration_helper::MAPPING_INPUT_HIDDEN_ID, PARAM_TEXT);
        }
    }

    /**
     * Get the label text of an element.
     * First lookign for "for" attribute, if not found, looks for preceding then following label element.
     *
     * @param DOMXpath $xpath the xpath object of the html.
     * @param DOMDocument $dom the dom object of the html
     * @param string $id the element id to look for its label text.
     *
     * @return string | null the label text or null if not found.
     */
    private function get_dom_label_text($xpath, $dom, $id) {
        $element = $dom->getElementById($id);
        $parent = $element->parentNode;
        $tagname = $element->tagName;
        $directlabelquery = "//label[contains(@for, '{$id}')]";
        $text = $this->get_text_from_domxpath($xpath, $directlabelquery, $parent);
        // If the text is empty as of now, that means there is no direct label tag "for" this element.
        // We try to look for any preceding or following label respectively.
        if (empty($text)) {
            // First preceding label.
            $precedinglabelquery = "//{$tagname}[@id='{$id}']/preceding-sibling::label";
            $text = $this->get_text_from_domxpath($xpath, $precedinglabelquery, $parent);

            // If it is still empty, then following label.
            if (empty($text)) {
                $followinglabelquery = "//{$tagname}[@id='{$id}']/following-sibling::label";

                // If it reaches here and is still null, then it is supposed to fallback to id!
                $text = $this->get_text_from_domxpath($xpath, $followinglabelquery, $parent);
            }
        }
        return $text;
    }

    /**
     * Get the label text of the parent element.
     * Considering the first label element of the parent.
     *
     * @param DOMXpath $xpath the xpath object of the html.
     * @param DOMDocument $dom the dom object of the html
     * @param string $id the element id to look for its parent's label text.
     *
     * @return string | null the label text or null if not found.
     */
    private function get_parent_label_text($xpath, $dom, $id) {
        $element = $dom->getElementById($id);
        $parent = $element->parentNode;
        return $this->get_text_from_domxpath($xpath, 'label[1]/text()', $parent);
    }

    /**
     * A helper function to evalute a DOMXpath query string and return the node value (text) if found.
     *
     * @param DOMXpath $xpath the xpath object of the html.
     * @param string $query the xpat query to look for.
     * @param DOMDocument $contextnode a portion or target dom object.
     *
     * @return string | null the text of the element, or null if not found.
     */
    private function get_text_from_domxpath($xpath, $query, $contextnode = null) {
        $elementnodes = $xpath->evaluate($query, $contextnode);
        if ($elementnodes->length > 0) {
            return (string) $elementnodes->item(0)->nodeValue;
        }
        return null;
    }
}
