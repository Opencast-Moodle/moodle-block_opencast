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
defined('MOODLE_INTERNAL') || die;

/**
 * Renderer class for block opencast.
 *
 * @package   block_customcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_renderer extends plugin_renderer_base
{
    const VISIBLE = 1;
    const MIXED_VISIBLITY = 3;
    const HIDDEN = 0;
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
                return $this->output->pix_icon('failed', get_string('ocstatefailed', 'block_opencast'), 'block_opencast');
            case 'PLANNED' :
                return $this->output->pix_icon('c/event', get_string('planned', 'block_opencast'));
            case 'CAPTURING' :
                return $this->output->pix_icon('capturing', get_string('ocstatecapturing', 'block_opencast'), 'block_opencast');
            case 'NEEDSCUTTING' :
                return $this->output->pix_icon('e/cut', get_string('ocstateneedscutting', 'block_opencast'));
            case 'DELETING' :
                return $this->output->pix_icon('t/delete', get_string('deleting', 'block_opencast'));
            case 'RUNNING' :
            case 'PAUSED' :
                return $this->output->pix_icon('processing', get_string('ocstateprocessing', 'block_opencast'), 'block_opencast');
            case 'SUCCEEDED' :
            default :
                return $this->output->pix_icon('succeeded', get_string('ocstatesucceeded', 'block_opencast'), 'block_opencast');
        }
    }

    /**
     * Render the whole block content.
     *
     * @param object $videodata data as a result from api query against opencast.
     */
    public function render_block_content($courseid, $videodata, $ocinstance, $rendername) {

        $html = '';

        $coursecontext = context_course::instance($courseid);

        if($rendername) {
            $html .= $this->output->heading($ocinstance->name);
        }

        if (has_capability('block/opencast:addvideo', $coursecontext)) {
            $addvideourl = new moodle_url('/blocks/opencast/addvideo.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstance->id));
            $addvideobutton = $this->output->single_button($addvideourl, get_string('addvideo', 'block_opencast'), 'get');
            $html .= html_writer::div($addvideobutton, 'opencast-addvideo-wrap overview');

            if (get_config('block_opencast', 'enable_opencast_studio_link_' . $ocinstance->id)) {
                $recordvideo = new moodle_url('/blocks/opencast/recordvideo.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstance->id));
                $recordvideobutton = $this->output->action_link($recordvideo, get_string('recordvideo', 'block_opencast'),
                    null, array('class' => 'btn btn-secondary', 'target' => '_blank'));
                $html .= html_writer::div($recordvideobutton, 'opencast-recordvideo-wrap overview');
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
                $listitems .= html_writer::tag('li', $icon . $video->title, array('class' => 'opencast-vlist-item'));
            }

            $html .= html_writer::tag('ul', $listitems, array('class' => 'opencast-vlist'));
        }

        $moretext = get_string('gotooverview', 'block_opencast');
        if ($videodata->more) {
            $moretext = get_string('morevideos', 'block_opencast');
        }
        $url = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstance->id));
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
        // Get understandable status string.
        $statusstring = \block_opencast\local\upload_helper::get_status_string($statuscode);

        // If needed, add the number of failed uploads.
        if ($countfailed > 1) {
            $statusstring .= '<br />' . get_string('failedtransferattempts', 'block_opencast', $countfailed);
        }

        // Return string.
        return $statusstring;
    }

    /**
     * Render the tabel of upload jobs.
     *
     * @param array uploadjobs array of uploadjob objects
     * @param bool showdeletebutton shows a delete button in the last column
     *
     * @return string
     */
    public function render_upload_jobs($uploadjobs, $showdeletebutton = true) {

        $table = new html_table();
        $table->head = array(
            get_string('hstart_date', 'block_opencast'),
            get_string('title', 'block_opencast'),
            get_string('presenterfile', 'block_opencast'),
            get_string('presentationfile', 'block_opencast'),
            get_string('status'),
            get_string('createdby', 'block_opencast'));
        if ($showdeletebutton) {
            $table->head[] = '';
        }

        foreach ($uploadjobs as $uploadjob) {

            $uploadjob->metadata ? $metadata = json_decode($uploadjob->metadata) : $metadata = '';
            $title = '';
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
            $row[] = $this->render_status($uploadjob->status, $uploadjob->countfailed);
            $row[] = fullname($uploadjob);
            if ($showdeletebutton) {
                $coursecontext = context_course::instance($uploadjob->courseid);
                // The one who is allowed to add the video is also allowed to delete the video before it is uploaded.
                $row[] = ($uploadjob->status == \block_opencast\local\upload_helper::STATUS_READY_TO_UPLOAD &&
                    has_capability('block/opencast:addvideo', $coursecontext)) ?
                    $this->render_delete_draft_icon($uploadjob->ocinstanceid, $uploadjob->courseid, $uploadjob->id) : '';
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Render the link to delete a group assignment.
     *
     * @param string $videoidentifier
     */
    public function render_delete_acl_group_assignment_icon($ocinstanceid, $courseid, $videoidentifier) {

        $url = new \moodle_url('/blocks/opencast/deleteaclgroup.php',
            array('identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
        $text = get_string('deleteaclgroup', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the link to change the visibility of a video.
     *
     * @param string $videoidentifier
     */
    public function render_change_visibility_icon($ocinstanceid, $courseid, $videoidentifier, $visible) {
        global $USER;
        $url = new \moodle_url('/blocks/opencast/changevisibility.php',
            array('identifier' => $videoidentifier, 'courseid' => $courseid,
                'visibility' => $visible, 'sesskey' => $USER->sesskey, 'ocinstanceid' => $ocinstanceid));

        switch ($visible) {
            case self::VISIBLE:
                $text = get_string('changevisibility_visible', 'block_opencast');
                $icon = $this->output->pix_icon('t/hide', $text);
                break;
            case self::MIXED_VISIBLITY:
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

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the information about the video before deleting the assignment of the event
     * to the course series.
     *
     * @param int $courseid
     * @param object $video
     * @return string
     */
    public function render_video_info($ocinstanceid, $courseid, $video) {

        if (!$video) {
            return get_string('videonotfound', 'block_opencast');
        }

        $html = get_string('deletegroupacldesc', 'block_opencast');

        $table = new \html_table();
        $table->head = array(
            get_string('hstart_date', 'block_opencast'),
            get_string('htitle', 'block_opencast'),
            get_string('hpublished', 'block_opencast'),
            get_string('hworkflow_state', 'block_opencast')
        );

        $row = array();

        $row[] = $this->render_created($video->start);
        $row[] = $video->title;
        $row[] = $this->render_publication_status($video->publication_status);
        $row[] = $this->render_processing_state_icon($video->processing_state);

        $table->data[] = $row;

        $html .= \html_writer::table($table);

        $label = get_string('dodeleteaclgroup', 'block_opencast');
        $params = array(
            'identifier' => $video->identifier,
            'courseid' => $courseid,
            'action' => 'delete',
            'ocinstanceid' => $ocinstanceid
        );
        $url = new \moodle_url('/blocks/opencast/deleteaclgroup.php', $params);
        $html .= $this->output->single_button($url, $label);

        return $html;
    }

    /**
     * Render the link to delete a group assignment.
     *
     * @param string $videoidentifier
     */
    public function render_delete_event_icon($ocinstanceid, $courseid, $videoidentifier) {

        $url = new \moodle_url('/blocks/opencast/deleteevent.php',
            array('identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
        $text = get_string('deleteevent', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the icon to add an LTI episode module.
     *
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return string
     */
    public function render_add_lti_episode_icon($ocinstanceid, $courseid, $episodeuuid) {
        $url = new \moodle_url('/blocks/opencast/addltiepisode.php', array('episodeuuid' => $episodeuuid, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
        $text = get_string('addltiepisode_addicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('share', $text, 'block_opencast');

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the icon to view an LTI episode module.
     *
     * @param int $moduleid
     *
     * @return string
     */
    public function render_view_lti_episode_icon($moduleid) {

        $url = new \moodle_url('/mod/lti/view.php', array('id' => $moduleid));
        $text = get_string('addltiepisode_viewicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('play', $text, 'block_opencast');

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the icon to add an Opencast Activity episode module.
     *
     * @param int $courseid
     * @param string $episodeuuid
     *
     * @return string
     */
    public function render_add_activity_episode_icon($ocinstanceid, $courseid, $episodeuuid) {
        $url = new \moodle_url('/blocks/opencast/addactivityepisode.php',
            array('episodeuuid' => $episodeuuid, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
        $text = get_string('addactivityepisode_addicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('share', $text, 'block_opencast');

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the icon to view an Opencast Activity episode module.
     *
     * @param int $moduleid
     *
     * @return string
     */
    public function render_view_activity_episode_icon($moduleid) {

        $url = new \moodle_url('/mod/opencast/view.php', array('id' => $moduleid));
        $text = get_string('addactivityepisode_viewicontitle', 'block_opencast');

        $icon = $this->output->pix_icon('play', $text, 'block_opencast');

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the link to delete a draft file.
     *
     * @param int $courseid
     * @param string $videoidentifier
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function render_delete_draft_icon($ocinstanceid, $courseid, $videoidentifier) {

        $url = new \moodle_url('/blocks/opencast/deletedraft.php',
            array('identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
        $text = get_string('dodeleteevent', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return \html_writer::link($url, $icon);
    }

    public function render_edit_functions($ocinstanceid, $courseid, $videoidentifier, $updatemetadata, $startworkflows, $coursecontext) {
        // Get the action menu options.
        $actionmenu = new action_menu();
        $actionmenu->set_alignment(action_menu::TL, action_menu::BL);
        $actionmenu->prioritise = true;
        $actionmenu->attributes['class'] .= ' inline-action-menu';

        if ($updatemetadata) {
            // Update metadata event.
            $actionmenu->add(new action_menu_link_secondary(
                new \moodle_url('/blocks/opencast/updatemetadata.php',
                    array('video_identifier' => $videoidentifier, 'courseid' => $courseid, 'ocinstanceid' => $ocinstanceid)),
                new pix_icon('t/editstring', get_string('updatemetadata_short', 'block_opencast')),
                get_string('updatemetadata_short', 'block_opencast')
            ));
        }

        if ($startworkflows && has_capability('block/opencast:startworkflow', $coursecontext)) {
            $actionmenu->add(new action_menu_link_secondary(
                new \moodle_url('#'),
                new pix_icon('t/collapsed', get_string('startworkflow', 'block_opencast')),
                get_string('startworkflow', 'block_opencast'),
                array('class' => 'start-workflow', 'data-id' => $videoidentifier)
            ));
        }

        return $this->render($actionmenu);
    }

    /**
     * Render the information about the video before finally delete it.
     *
     * @param int $courseid
     * @param object $video
     * @return string
     */
    public function render_video_deletion_info($ocinstanceid, $courseid, $video) {

        if (!$video) {
            return get_string('videonotfound', 'block_opencast');
        }

        $html = $this->output->notification(get_string('deleteeventdesc', 'block_opencast'), 'error');

        $table = new \html_table();
        $table->head = array();
        $table->head [] = get_string('hstart_date', 'block_opencast');
        $table->head [] = get_string('htitle', 'block_opencast');
        if (get_config('block_opencast', 'showpublicationchannels_' . $ocinstanceid)) {
            $table->head [] = get_string('hpublished', 'block_opencast');
        };
        $table->head [] = get_string('hworkflow_state', 'block_opencast');

        $row = array();

        $row[] = $this->render_created($video->start);
        $row[] = $video->title;
        if (get_config('block_opencast', 'showpublicationchannels_' . $ocinstanceid)) {
            $row[] = $this->render_publication_status($video->publication_status);
        }
        $row[] = $this->render_processing_state_icon($video->processing_state);

        $table->data[] = $row;

        $html .= \html_writer::table($table);

        $label = get_string('dodeleteevent', 'block_opencast');
        $params = array(
            'identifier' => $video->identifier,
            'courseid' => $courseid,
            'action' => 'delete', 'ocinstanceid' => $ocinstanceid
        );
        $url = new \moodle_url('/blocks/opencast/deleteevent.php', $params);
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

    public function render_series_settings_actions(int $ocinstanceid, int $courseid, bool $createseries, bool $editseries): string {
        $context = new \stdClass();
        $context->hasanyactions = false;
        if ($createseries) {
            $context->hasanyactions = true;
            $url = new moodle_url('/blocks/opencast/createseries.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
            $context->createseriesurl = $url->out();
        }
        if ($editseries) {
            $context->hasanyactions = true;
            $url = new moodle_url('/blocks/opencast/editseries.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));
            $context->editseriesurl = $url->out();
        }
        return $this->render_from_template('block_opencast/series_settings_actions', $context);
    }

    /**
     * Display the lti form.
     *
     * @param object $data The prepared variables.
     * @return string
     */
    public function render_lti_form($endpoint, $params) {
        $content = "<form action=\"" . $endpoint .
            "\" name=\"ltiLaunchForm\" id=\"ltiLaunchForm\" method=\"post\" encType=\"application/x-www-form-urlencoded\">\n";

        // Construct html form for the launch parameters.
        foreach ($params as $key => $value) {
            $key = htmlspecialchars($key);
            $value = htmlspecialchars($value);
            $content .= "<input type=\"hidden\" name=\"{$key}\"";
            $content .= " value=\"";
            $content .= $value;
            $content .= "\"/>\n";
        }
        $content .= "</form>\n";

        return $content;
    }

    public function render_download_event_icon($ocinstanceid, $courseid, $video) {
        // Get the action menu options.
        $actionmenu = new action_menu();
        $actionmenu->set_alignment(action_menu::TL, action_menu::BL);
        $actionmenu->prioritise = true;
        $actionmenu->actionicon = new pix_icon('t/down', get_string('downloadvideo', 'block_opencast'));
        $actionmenu->set_menu_trigger(' ');
        $actionmenu->attributes['class'] .= ' download-action-menu';

        foreach ($video->publications as $publication) {
            if ($publication->channel == get_config('block_opencast', 'download_channel_' . $ocinstanceid)) {
                foreach ($publication->media as $media) {
                    $name = ucwords(explode('/', $media->flavor)[0]) . ' (' . $media->width . 'x' . $media->height . ')';
                    $actionmenu->add(new action_menu_link_secondary(
                        new \moodle_url('/blocks/opencast/downloadvideo.php',
                            array('video_identifier' => $video->identifier, 'courseid' => $courseid, 'mediaid' => $media->id, 'ocinstanceid' => $ocinstanceid)),
                        null,
                        $name
                    ));
                }
            }
        }

        return $this->render($actionmenu);
    }

    public function render_report_problem_icon($identifier) {
        $icon = $this->output->pix_icon('t/message', get_string('reportproblem_modal_title', 'block_opencast'));
        return \html_writer::link('#', $icon, array('class' => 'report-problem', 'data-id' => $identifier));
    }
}
