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

use tool_opencast\local\apibridge;
use tool_opencast\local\settings_api;

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

        if (has_capability('tool/opencast:addvideo', $coursecontext) && $SITE->id != $courseid) {
            $addvideourl = new moodle_url('/admin/tool/opencast/addvideo.php',
                ['courseid' => $courseid, 'ocinstanceid' => $ocinstance->id]);
            $addvideobutton = $this->output->single_button($addvideourl, get_string('addvideo', 'block_opencast'), 'get');
            $html .= html_writer::div($addvideobutton, 'opencast-addvideo-wrap overview');

            // Show "Add videos (batch)" button.
            if (get_config('tool_opencast', 'batchuploadenabled_' . $ocinstance->id)) {
                $batchuploadurl = new moodle_url('/admin/tool/opencast/batchupload.php',
                    ['courseid' => $courseid, 'ocinstanceid' => $ocinstance->id]);
                $batchuploadbutton = $this->output->single_button($batchuploadurl,
                    get_string('batchupload', 'block_opencast'), 'get');
                $html .= html_writer::div($batchuploadbutton, 'opencast-batchupload-wrap overview');
            }

            if (get_config('tool_opencast', 'enable_opencast_studio_link_' . $ocinstance->id)) {
                // Initialize the link target to open in the same tab.
                $target = '_self';
                // Check for the admin config to set the link target.
                if (get_config('tool_opencast', 'open_studio_in_new_tab_' . $ocinstance->id)) {
                    $target = '_blank';
                }
                // If LTI credentials are given, use LTI. If not, directly forward to Opencast studio.
                $apibridge = apibridge::get_instance($ocinstance->id);
                if (empty($apibridge->get_lti_consumerkey())) {
                    if (empty(get_config('tool_opencast', 'opencast_studio_baseurl_' . $ocinstance->id))) {
                        $endpoint = settings_api::get_apiurl($ocinstance->id);
                    } else {
                        $endpoint = get_config('tool_opencast', 'opencast_studio_baseurl_' . $ocinstance->id);
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
                    $recordvideo = new moodle_url('/admin/tool/opencast/recordvideo.php',
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
        $url = new moodle_url('/admin/tool/opencast/index.php', ['courseid' => $courseid, 'ocinstanceid' => $ocinstance->id]);

        // In admin page, we redirect to the series overview page to manage series from there.
        if ($SITE->id == $courseid) {
            $url = new moodle_url('/admin/tool/opencast/overview.php', ['ocinstanceid' => $ocinstance->id]);
        }
        $link = html_writer::link($url, $moretext);
        $html .= html_writer::div($link, 'opencast-more-wrap');

        return $html;
    }

}
