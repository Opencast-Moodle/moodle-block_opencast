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
use tool_opencast\seriesmapping;

/**
 * Configuration of block_opencast.
 */
class block_opencast extends block_base {

    /**
     * Initializes the block.
     * @throws coding_exception
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_opencast');
    }

    /**
     * Block can appear on the dashboard and course pages.
     */
    public function applicable_formats() {
        return array('all' => false, 'course' => true, 'my' => true);
    }

    /**
     * Block cannot be added twice to a page.
     * @return false
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Block has own settings.
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Get the block's content (depends on where it is embedded).
     * @return stdClass|stdObject|null
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_content() {
        global $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance->parentcontextid)) {
            return $this->content;
        }

        $parentcontext = context::instance_by_id($this->instance->parentcontextid);

        $ocinstances = \tool_opencast\local\settings_api::get_ocinstances();
        $ocinstances = array_filter($ocinstances, function ($oci) {
            return $oci->isvisible;
        });
        $rendername = count($ocinstances) > 1;

        if ($parentcontext->contextlevel === CONTEXT_USER) {
            foreach ($ocinstances as $instance) {
                if ($rendername) {
                    $this->content->text .= html_writer::link(new moodle_url('/blocks/opencast/overview.php',
                            array('ocinstanceid' => $instance->id)),
                            get_string('seriesoverviewof', 'block_opencast', $instance->name)) . '<br>';
                } else {
                    $this->content->text .= html_writer::link(new moodle_url('/blocks/opencast/overview.php',
                        array('ocinstanceid' => $instance->id)),
                        get_string('seriesoverview', 'block_opencast'));
                }
            }
        } else {

            $coursecontext = context_course::instance($COURSE->id);

            if (!has_capability('block/opencast:viewunpublishedvideos', $coursecontext)) {
                return $this->content;
            }

            $renderer = $this->page->get_renderer('block_opencast');

            $cache = cache::make('block_opencast', 'videodata');
            if ($result = $cache->get($COURSE->id)) {
                if ($result->timevalid > time()) {
                    // If cache for course is set and still valid.
                    $videos = $result->videos;
                }
            }

            if (!isset($videos)) {
                $videos = array();

                foreach ($ocinstances as $instance) {
                    try {
                        if ($instance->isvisible) {
                            $apibridge = \block_opencast\local\apibridge::get_instance($instance->id);
                            $videos[$instance->id] = $apibridge->get_block_videos($COURSE->id);
                        }
                    } catch (opencast_connection_exception $e) {
                        $videos[$instance->id] = new stdClass();
                        $videos[$instance->id]->error = $e->getMessage();
                    }
                }
                $cacheobj = new stdClass();
                $cacheobj->timevalid = time() + get_config('block_opencast', 'cachevalidtime');
                $cacheobj->videos = $videos;
                $cache->set($COURSE->id, $cacheobj);
            }

            foreach ($ocinstances as $instance) {
                if ($instance->isvisible) {
                    $this->content->text .= $renderer->render_block_content($COURSE->id, $videos[$instance->id],
                        $instance, $rendername);
                }
            }
        }

        return $this->content;
    }

    /**
     * Deletes the series mappings when a block is deleted.
     * @return bool
     * @throws coding_exception
     */
    public function instance_delete() {
        global $COURSE;
        $success = true;

        $mappings = seriesmapping::get_records(array('courseid' => $COURSE->id));
        foreach ($mappings as $mapping) {
            if (!$mapping->delete()) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Do any additional initialization you may need at the time a new block instance is created
     * @return boolean
     */
    function instance_create() {
        global $DB;

        if ($this->instance_allow_multiple() === false) {
            $ocblockinstances = $DB->get_records('block_instances',
                    ['blockname' => 'opencast', 'parentcontextid' => $this->instance->parentcontextid]);
            if (count($ocblockinstances) > 1) {
                $idstoremove = array_keys($ocblockinstances);
                sort($idstoremove);
                array_shift($idstoremove);
                foreach ($idstoremove as $id) {
                    $DB->delete_records('block_instances', ['id' => $id]);
                }
            }
        }
        return true;
    }
}
