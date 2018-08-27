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
 * Form for editing HTML block instances.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class block_opencast extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_opencast');
    }

    public function applicable_formats() {
        return array('course' => true);
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $PAGE, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        $coursecontext = context_course::instance($COURSE->id);

        if (!has_capability('block/opencast:viewunpublishedvideos', $coursecontext)) {
            return $this->content;
        }

        $renderer = $PAGE->get_renderer('block_opencast');
        $apibridge = \block_opencast\local\apibridge::get_instance();
        try {
            $videos = $apibridge->get_block_videos($COURSE->id);
        } catch (\moodle_exception $e) {
            $videos = new \stdClass();
            $videos->error = $e->getmessage();
        }
        $this->content->text = $renderer->render_block_content($COURSE->id, $videos);
        return $this->content;
    }

}
