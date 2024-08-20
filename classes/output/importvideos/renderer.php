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

use block_opencast\local\importvideos_coursesearch;
use core_backup_renderer;
use html_writer;
use moodle_url;

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
class renderer extends core_backup_renderer {

    /**
     * Renderer to display the import course selector.
     * This function is a modified version of import_course_selector from core_backup_renderer
     * with the goal to adapt the widget to our needs.
     *
     * @param moodle_url $nextstageurl
     * @param importvideos_coursesearch|null $courses
     *
     * @return string
     */
    public function importvideos_coursesearch(moodle_url $nextstageurl,
                                              ?importvideos_coursesearch $courses = null) {
        $html = html_writer::start_tag('div', ['class' => 'import-course-selector']);
        $html .= $this->wizard_intro_notification(
            get_string('importvideos_wizardstep1intro', 'block_opencast'));
        $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $nextstageurl->out()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'step', 'value' => 1]);
        foreach ($nextstageurl->params() as $key => $value) {
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
        }
        $html .= html_writer::start_tag('div', ['class' => 'ics-existing-course']);
        $html .= $this->backup_detail_pair('', $this->render_import_course_search($courses));
        $attrs = ['type' => 'submit',
            'value' => get_string('importvideos_wizardstepbuttontitlecontinue', 'block_opencast'),
            'class' => 'btn btn-primary', ];
        $html .= $this->backup_detail_pair('', html_writer::empty_tag('input', $attrs));
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');
        return $html;
    }

    /**
     * Renderer to render the HTML code of a course menu entry.
     *
     * @param Object $course
     *
     * @return string
     */
    public function course_menu_entry($course) {
        // Add the course fullname.
        $entrystring = $course->fullname;

        // Add the course shortname, if set.
        if (!empty($course->shortname)) {
            $entrystring .= html_writer::empty_tag('br');
            $entrystring .= html_writer::start_tag('small');
            $entrystring .= $course->shortname;
            $entrystring .= html_writer::end_tag('small');
        }

        // Finally, return the menu entry code.
        return $entrystring;
    }

    /**
     * Renderer to render the HTML code of a course video menu entry.
     *
     * @param Object $video
     *
     * @return string
     */
    public function course_video_menu_entry($video) {
        // Add the video title.
        $entrystring = $video->title;

        // Add the start date, if set.
        if (!empty($video->start)) {
            $entrystring .= html_writer::empty_tag('br');
            $entrystring .= html_writer::start_tag('small');
            $entrystring .= get_string('startDate', 'block_opencast') . ': ';
            $entrystring .= userdate(strtotime($video->start), get_string('strftimedatetime', 'langconfig'));
            $entrystring .= html_writer::end_tag('small');
        }

        // Add the presenter(s), if set.
        if (count($video->presenter) > 0) {
            $entrystring .= html_writer::empty_tag('br');
            $entrystring .= html_writer::start_tag('small');
            $entrystring .= get_string('creator', 'block_opencast') . ': ';
            $entrystring .= implode(', ', $video->presenter);
            $entrystring .= html_writer::end_tag('small');
        }

        // Finally, return the menu entry code.
        return $entrystring;
    }

    /**
     * Renderer to render the HTML code of the progress bar.
     *
     * @param int $currentstep
     * @param int $maxsteps
     * @param bool $hasstep3
     *
     * @return string
     */
    public function progress_bar($currentstep = 1, $maxsteps = 4, $hasstep3 = true) {
        // If we don't have step 3, we have to respect that.
        if ($hasstep3 == false) {
            // The whole progress bar has one step less.
            $maxsteps -= 1;
            // After step 3, the current step is one step less.
            if ($currentstep > 3) {
                $currentstep -= 1;
            }
        }

        // Compose progress bar (based on Bootstrap).
        $progressbar = html_writer::start_div('progress my-3');
        $progressbar .= html_writer::start_div('progress-bar',
            ['role' => 'progressbar',
                'style' => 'width: ' . (floor(($currentstep / $maxsteps) * 100)) . '%',
                'aria-valuenow' => $currentstep,
                'aria-valuemin' => '0',
                'aria-valuemax' => $maxsteps, ]);
        $progressbar .= html_writer::start_span('text-left pl-2');
        $progressbar .= get_string('importvideos_progressbarstep', 'block_opencast',
            ['current' => $currentstep, 'last' => $maxsteps]);
        $progressbar .= html_writer::end_span('');
        $progressbar .= html_writer::end_div();
        $progressbar .= html_writer::end_div();

        // Finally, return the progress bar code.
        return $progressbar;
    }

    /**
     * Renderer to render an intro notification for the wizard.
     *
     * @param string $intromessage
     *
     * @return string
     */
    public function wizard_intro_notification($intromessage) {
        // Compose notification.
        $notification = html_writer::start_div('alert alert-info');
        $notification .= $intromessage;
        $notification .= html_writer::end_div();

        // Finally, return the intro notification code.
        return $notification;
    }

    /**
     * Renderer to render an error notification for the wizard.
     *
     * @param string $errormessage
     *
     * @return string
     */
    public function wizard_error_notification($errormessage) {
        // Compose notification.
        $notification = html_writer::start_div('alert alert-danger');
        $notification .= $errormessage;
        $notification .= html_writer::end_div();

        // Finally, return the error notification code.
        return $notification;
    }

    /**
     * Renderer to render the HTML code of a series menu entry.
     *
     * @param Object $series
     *
     * @return string
     */
    public function series_menu_entry($series) {
        // Add the series title.
        $entrystring = $series->title;

        $entrystring .= html_writer::empty_tag('br');
        $entrystring .= html_writer::start_tag('small');
        $entrystring .= $series->identifier;
        $entrystring .= html_writer::end_tag('small');

        // Finally, return the menu entry code.
        return $entrystring;
    }

    /**
     * Render an unordered list of course videos menu entry.
     *
     * @param array $arrayvideoentrystrings to use as list elements
     * @return string
     */
    public function course_videos_list_entry($arrayvideoentrystrings) {
        // Add the video list.
        $entrystring = '';

        if (count($arrayvideoentrystrings) == 0) {
            return $entrystring;
        }
        // Loop through the videos to generate a list.
        foreach ($arrayvideoentrystrings as $videoentrystring) {
            $entrystring .= html_writer::tag('li', $videoentrystring, ['class' => 'mb-2']);
        }

        // Finally, return the video entry list code.
        return html_writer::tag('ul', $entrystring);
    }

    /**
     * Renderer to render a warning notification for the wizard.
     *
     * @param string $warningmessage
     *
     * @return string
     */
    public function wizard_warning_notification($warningmessage) {
        // Compose notification.
        $notification = html_writer::start_div('alert alert-warning');
        $notification .= $warningmessage;
        $notification .= html_writer::end_div();

        // Finally, return the error notification code.
        return $notification;
    }

}
