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
class block_opencast_renderer extends plugin_renderer_base {
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
    public function render_block_content($courseid, $videodata) {

        $html = '';

        $coursecontext = context_course::instance($courseid);

        if (has_capability('block/opencast:addvideo', $coursecontext)) {
            $addvideourl = new moodle_url('/blocks/opencast/addvideo.php', array('courseid' => $courseid));
            $addvideobutton = $this->output->single_button($addvideourl, get_string('addvideo', 'block_opencast'));
            $html .= html_writer::div($addvideobutton, 'opencast-addvideo-wrap');

            if (get_config('block_opencast', 'enable_opencast_studio_link')) {
                $recordvideo = new moodle_url('/blocks/opencast/recordvideo.php', array('courseid' => $courseid));
                $recordvideobutton = $this->output->single_button($recordvideo, get_string('recordvideo', 'block_opencast'));
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
        $url = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));
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
     *
     * @return string
     */
    public function render_status($statuscode) {
        return \block_opencast\local\upload_helper::get_status_string($statuscode);
    }

    /**
     * Render the tabel of upload jobs.
     *
     * @param  array uploadjobs array of uploadjob objects
     *
     * @return string
     */
    public function render_upload_jobs($uploadjobs) {

        $table = new html_table();
        $table->head = array(
            get_string('date'),
            get_string('title', 'block_opencast'),
            get_string('presenterfilename', 'block_opencast'),
            get_string('presenterfilesize', 'block_opencast'),
            get_string('presentationfilename', 'block_opencast'),
            get_string('presentationfilesize', 'block_opencast'),
            get_string('status'),
            get_string('countfailed', 'block_opencast'),
            get_string('createdby', 'block_opencast'));

        foreach ($uploadjobs as $uploadjob) {

            $uploadjob->metadata ? $metadata = json_decode($uploadjob->metadata): $metadata = '';
            $title = '';
            if ($metadata) {
                foreach ($metadata as $ms) {
                    if ($ms->id == 'title') {
                        $title = $ms->value;
                        break;
                    }
                }
            }

            $row = [];
            $row[] = userdate($uploadjob->timecreated, get_string('strftimedatetime', 'langconfig'));
            $row[] = $title;
            $row[] = $uploadjob->presenter_filename;
            $row[] = $uploadjob->presenter_filesize ? display_size($uploadjob->presenter_filesize) : "";
            $row[] = $uploadjob->presentation_filename;
            $row[] = $uploadjob->presentation_filesize ? display_size($uploadjob->presentation_filesize) : "";
            $row[] = $this->render_status($uploadjob->status);
            $row[] = $uploadjob->countfailed;
            $row[] = fullname($uploadjob);

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Render the link to delete a group assignment.
     *
     * @param string $videoidentifier
     */
    public function render_delete_acl_group_assignment_icon($courseid, $videoidentifier) {

        $url = new \moodle_url('/blocks/opencast/deleteaclgroup.php',
            array('identifier' => $videoidentifier, 'courseid' => $courseid));
        $text = get_string('deleteaclgroup', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the link to change the visibility of a video.
     *
     * @param string $videoidentifier
     */
    public function render_change_visibility_icon($courseid, $videoidentifier, $visible) {
        global $USER;
        $url = new \moodle_url('/blocks/opencast/changevisibility.php',
            array('identifier' => $videoidentifier, 'courseid' => $courseid, 'visibility' => $visible, 'sesskey' => $USER->sesskey));

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
    public function render_video_info($courseid, $video) {

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
            'courseid'   => $courseid,
            'action'     => 'delete'
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
    public function render_delete_event_icon($courseid, $videoidentifier) {

        $url = new \moodle_url('/blocks/opencast/deleteevent.php', array('identifier' => $videoidentifier, 'courseid' => $courseid));
        $text = get_string('deleteevent', 'block_opencast');

        $icon = $this->output->pix_icon('t/delete', $text);

        return \html_writer::link($url, $icon);
    }

    //metadata
    /**
     * Render the link to update metadata.
     *
     * @param string $videoidentifier
     */
    public function render_update_metadata_event_icon($courseid, $videoidentifier) {

        $url = new \moodle_url('/blocks/opencast/updatemetadata.php', array('video_identifier' => $videoidentifier, 'courseid' => $courseid));
        $text = get_string('updatemetadata', 'block_opencast');

        $icon = $this->output->pix_icon('t/edit', $text);

        return \html_writer::link($url, $icon);
    }

    /**
     * Render the information about the video before finally delete it.
     *
     * @param int $courseid
     * @param object $video
     * @return string
     */
    public function render_video_deletion_info($courseid, $video) {

        if (!$video) {
            return get_string('videonotfound', 'block_opencast');
        }

        $html = $this->output->notification(get_string('deleteeventdesc', 'block_opencast'), 'error');

        $table = new \html_table();
        $table->head = array();
        $table->head []= get_string('hstart_date', 'block_opencast');
        $table->head []= get_string('htitle', 'block_opencast');
        if (get_config('block_opencast', 'showpublicationchannels')) {
            $table->head [] = get_string('hpublished', 'block_opencast');
        };
        $table->head []= get_string('hworkflow_state', 'block_opencast');

        $row = array();

        $row[] = $this->render_created($video->start);
        $row[] = $video->title;
        if (get_config('block_opencast', 'showpublicationchannels')) {
            $row[] = $this->render_publication_status($video->publication_status);
        }
        $row[] = $this->render_processing_state_icon($video->processing_state);

        $table->data[] = $row;

        $html .= \html_writer::table($table);

        $label = get_string('dodeleteevent', 'block_opencast');
        $params = array(
            'identifier' => $video->identifier,
            'courseid' => $courseid,
            'action' => 'delete'
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

    public function render_series_settings_actions(int $courseid, bool $createseries, bool $editseries): string {
        $context = new \stdClass();
        $context->hasanyactions = false;
        if ($createseries) {
            $context->hasanyactions = true;
            $url = new moodle_url('/blocks/opencast/createseries.php', array('courseid' => $courseid));
            $context->createseriesurl = $url->out();
        }
        if ($editseries) {
            $context->hasanyactions = true;
            $url = new moodle_url('/blocks/opencast/editseries.php', array('courseid' => $courseid));
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
        $content = "<form action=\"" . urlencode($endpoint) .
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

}
