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

use block_opencast\local\apibridge;
use block_opencast\opencast_connection_exception;
use tool_opencast\local\settings_api;
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
        return ['all' => false, 'course' => true, 'my' => true];
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

        $ocinstances = settings_api::get_ocinstances();
        $ocinstances = array_filter($ocinstances, function ($oci) {
            return $oci->isvisible;
        });
        $rendername = count($ocinstances) > 1;

        if ($parentcontext->contextlevel === CONTEXT_USER) {
            foreach ($ocinstances as $instance) {
                if ($rendername) {
                    $this->content->text .= html_writer::link(new moodle_url('/blocks/opencast/overview.php',
                            ['ocinstanceid' => $instance->id]),
                            get_string('seriesoverviewof', 'block_opencast', $instance->name)) . '<br>';
                } else {
                    $this->content->text .= html_writer::link(new moodle_url('/blocks/opencast/overview.php',
                        ['ocinstanceid' => $instance->id]),
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
                $videos = [];

                foreach ($ocinstances as $instance) {
                    try {
                        if ($instance->isvisible) {
                            $apibridge = apibridge::get_instance($instance->id);
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
     * Perform actions when the block instance is deleting.
     * @see block_opencast_pre_block_delete method in lib.php, by which completes this function purpose by providing
     * a new confirmation message.
     * @return void
     */
    public function instance_delete() {
        // Please see block_opencast_pre_block_delete before implementing anything here.
    }

    /**
     * Do any additional initialization you may need at the time a new block instance is created:
     * If the multiple is not allowed, we only allow one (first) block instance in a context. Others will be deleted.
     * @return boolean
     */
    public function instance_create() {
        global $DB;

        if ($this->instance_allow_multiple() === false) {
            $ocblockinstances = $DB->get_records('block_instances',
                ['blockname' => 'opencast', 'parentcontextid' => $this->instance->parentcontextid]);
            if (count($ocblockinstances) > 1) {
                $idstoremove = array_keys($ocblockinstances);
                sort($idstoremove);
                array_shift($idstoremove);
                foreach ($idstoremove as $id) {
                    blocks_delete_instance($ocblockinstances[$id]);
                }
            }
        }
        return true;
    }

    /**
     * Return a block_contents object representing the full contents of this block.
     *
     * This internally calls ->get_content(), and then adds the editing controls etc.
     *
     * Overwritten method from parent class (block_base)
     *
     * @param \core_renderer $output
     * @return block_contents a representation of the block, for rendering.
     */
    public function get_content_for_output($output) {

        // Get the block_contents object from parent class.
        $bc = parent::get_content_for_output($output);

        // We prepare the data to use and replace the existing action link contents.
        $title = $this->title;
        $defaultdeletestr = get_string('deleteblock', 'block', $this->title);

        // Check if the block_contents has controls.
        if (!empty($bc->controls)) {

            // We filter the controls to find the delete action link.
            $deleteactionfiltered = array_filter($bc->controls, function ($actionlink) use ($defaultdeletestr, $title) {
                // Get the text from action link.
                $actionlinktext = $actionlink->text;
                // Get the text if it is a type of lang_string via __toString.
                if ($actionlinktext instanceof \lang_string) {
                    $actionlinktext = $actionlinktext->__toString();
                }
                return $actionlinktext === $defaultdeletestr;
            });

            // In case the delete action link could be found, we try to replace its properties.
            if (!empty($deleteactionfiltered)) {
                $index = key($deleteactionfiltered);
                $deleteaction = reset($deleteactionfiltered);
                // Replace the action link's text.
                if (isset($deleteaction->text)) {
                    $deleteaction->text = get_string('delete_block_action_item_text', 'block_opencast');
                }
                if (isset($deleteaction->attributes)) {
                    if (isset($deleteaction->attributes['data-modal-title-str'])) {
                        $deleteaction->attributes['data-modal-title-str'] = json_encode(
                            ['deletecheck_title_modal', 'block_opencast']
                        );
                    }
                    if (isset($deleteaction->attributes['data-modal-content-str'])) {
                        $deleteaction->attributes['data-modal-content-str'] = json_encode(
                            ['deletecheck_content_modal', 'block_opencast']
                        );
                    }
                }
                $bc->controls[$index] = $deleteaction;
            }
        }
        return $bc;
    }
}
