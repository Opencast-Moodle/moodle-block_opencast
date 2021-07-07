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

use block_opencast\opencast_connection_exception;

defined('MOODLE_INTERNAL') || die();

class block_opencast extends block_base
{

    public function init() {
        $this->title = get_string('pluginname', 'block_opencast');
    }

    public function applicable_formats() {
        return array('course' => true, 'course-editsection' => false);
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $COURSE;

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

        $renderer = $this->page->get_renderer('block_opencast');

        $ocinstances = json_decode(get_config('tool_opencast', 'ocinstances'));
        $rendername = count($ocinstances) > 1;

        # todo check isvisible
        $cache = cache::make('block_opencast', 'videodata');
        if ($result = $cache->get($COURSE->id)) {
            if ($result->timevalid > time()) {
                // If cache for course is set and still valid.
                $videos = $result->videos;
            }
        }

        if (!isset($videos)) {
            $videos = array();

            try {
                foreach ($ocinstances as $instance) {
                    $apibridge = \block_opencast\local\apibridge::get_instance($instance->id);
                    $videos[$instance->id] = $apibridge->get_block_videos($COURSE->id);
                }
                $cacheobj = new stdClass();
                $cacheobj->timevalid = time() + get_config('block_opencast', 'cachevalidtime');
                $cacheobj->videos = $videos;
                $cache->set($COURSE->id, $cacheobj);
            } catch (opencast_connection_exception $e) {
                $videos = new \stdClass();
                $videos->error = $e->getmessage();
            }
        }

        foreach ($ocinstances as $instance) {
            $this->content->text .= $renderer->render_block_content($COURSE->id, $videos[$instance->id], $instance, $rendername);
        }

        return $this->content;
    }
}
