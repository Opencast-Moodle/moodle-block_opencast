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
 * Changes the visibility of a video.
 *
 * @package    block_opencast
 * @copyright  2018 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once('./renderer.php');

global $PAGE, $OUTPUT, $CFG;

$identifier = required_param('identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$visible = required_param('visible', PARAM_BOOL);

require_login($courseid, false);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$opencast = \block_opencast\local\apibridge::get_instance();
$video = $opencast->get_opencast_video($identifier);

if (confirm_sesskey()) {
    // Workflow is not set.
    if (get_config('block_opencast', 'workflow_roles') == "") {
        $message = get_string('workflownotdefined', 'block_opencast', $video->video);
        redirect($redirecturl, $message, null, \core\notification::ERROR);
    }

    if ($video->video) {
        // A workflow is currently running.
        if ($video->video->processing_state !== "SUCCEEDED" && $video->video->processing_state !== "FAILED") {
            $message = get_string('workflowisrunning', 'block_opencast', $video->video);
            redirect($redirecturl, $message, null, \core\notification::ERROR);
        }

        if ($visible === block_opencast_renderer::VISIBLE || $visible == block_opencast_renderer::MIXED_VISIBLITY) {
            if ($opencast->delete_not_permanent_acl_roles($video->video->identifier, $courseid)) {
                $message = get_string('aclrolesdeleted', 'block_opencast', $video->video);
                $status = \core\notification::SUCCESS;
            } else {
                $message = get_string('aclrolesdeletederror', 'block_opencast', $video->video);
                $status = \core\notification::ERROR;
            }
        } else {
            if ($opencast->ensure_acl_group_assigned($video->video->identifier, $courseid)) {
                $message = get_string('aclrolesadded', 'block_opencast', $video->video);
                $status = \core\notification::SUCCESS;
            } else {
                $message = get_string('aclrolesaddederror', 'block_opencast', $video->video);
                $status = \core\notification::ERROR;
            }
        }

        redirect($redirecturl, $message, null, $status);
    }

    $message = get_string('videonotfound', 'block_opencast');
    redirect($redirecturl, $message);
}