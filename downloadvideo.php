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
 *
 * @package    block_opencast
 * @copyright  2021 Tamara Gunkel, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\apibridge;

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$videoid = required_param('video_identifier', PARAM_ALPHANUMEXT);
$mediaid = required_param('mediaid', PARAM_ALPHANUMEXT);
$ocinstanceid = optional_param('ocinstanceid', \tool_opencast\local\settings_api::get_default_ocinstance()->id, PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/downloadvideo.php',
    array('courseid' => $courseid, 'video_identifier' => $videoid, 'ocinstanceid' => $ocinstanceid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid, 'ocinstanceid' => $ocinstanceid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('addvideo', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('addvideo', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:downloadvideo', $coursecontext);

$apibridge = apibridge::get_instance($ocinstanceid);
$result = $apibridge->get_opencast_video($videoid, true);
if (!$result->error) {
    $video = $result->video;
    if ($video->is_downloadable) {
        foreach ($video->publications as $publication) {
            if ($publication->channel == get_config('block_opencast', 'download_channel_' . $ocinstanceid)) {
                foreach ($publication->media as $media) {
                    if ($media->id === $mediaid) {
                        $downloadurl = $media->url;
                        $mimetype = $media->mediatype;
                        $size = $media->size;
                        break 2;
                    }
                }
            }
        }
        $filename = $video->title . '.' . pathinfo($downloadurl, PATHINFO_EXTENSION);

        header('Content-Description: Download Video');
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $size);


        if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: private, max-age=10, no-transform');
            header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
            header('Pragma: ');
        } else { // Normal http - prevent caching at all cost.
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0, no-transform');
            header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
            header('Pragma: no-cache');
        }

        readfile($downloadurl);
    } else {
        redirect($redirecturl,
            get_string('video_not_downloadable', 'block_opencast'),
            null,
            \core\output\notification::NOTIFY_ERROR);
    }
} else {
    redirect($redirecturl,
        get_string('video_retrieval_failed', 'block_opencast'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}
