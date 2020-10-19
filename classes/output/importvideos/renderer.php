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
* Renderer class for the import videos course search feature.
*
* @package    block_opencast
* @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

namespace block_opencast\output\importvideos;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');
require_once($CFG->dirroot . '/backup/util/ui/renderer.php');

/**
 * Renderer class for the import videos course search feature.
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \core_backup_renderer
{
    /**
     * Renderer to display the import course selector.
     * This function is a modified version of import_course_selector from core_backup_renderer
     * with the goal to adapt the widget to our needs.
     *
     * @param moodle_url $nextstageurl
     * @param \block_opencast\local\importvideos_coursesearch $courses
     * @return string
     */
    public function importvideos_coursesearch(\moodle_url $nextstageurl,
            \block_opencast\local\importvideos_coursesearch $courses = null) {
        $html  = \html_writer::start_tag('div', array('class' => 'import-course-selector'));
        $html .= \block_opencast\local\importvideosmanager::render_wizard_intro_notification(
                get_string('importvideos_wizardstep1intro', 'block_opencast'));
        $html .= \html_writer::start_tag('form', array('method' => 'post', 'action' => $nextstageurl->out()));
        $html .= \html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'step', 'value' => 1));
        foreach ($nextstageurl->params() as $key => $value) {
            $html .= \html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $key, 'value' => $value));
        }
        $html .= \html_writer::start_tag('div', array('class' => 'ics-existing-course'));
        $html .= $this->backup_detail_pair('', $this->render_import_course_search($courses));
        $attrs = array('type' => 'submit',
                       'value' => get_string('importvideos_wizardstepbuttontitlecontinue', 'block_opencast'),
                       'class' => 'btn btn-primary');
        $html .= $this->backup_detail_pair('', \html_writer::empty_tag('input', $attrs));
        $html .= \html_writer::end_tag('div');
        $html .= \html_writer::end_tag('form');
        $html .= \html_writer::end_tag('div');
        return $html;
    }
}
