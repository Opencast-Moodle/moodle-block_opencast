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
 * * @package    block_opencast
 * @copyright  2019 Farbod Zamani, ELAN e.V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\upload_helper;

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/updatemetadata.php',  array('video_identifier' => $identifier, 'courseid' => $courseid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('updatemetadata', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$opencast = \block_opencast\local\apibridge::get_instance();
$metadata = $opencast->get_event_metadata($identifier, '?type=dublincore/episode');
$metadata_catalog = upload_helper::get_opencast_metadata_catalog();

$updatemetadataform = new \block_opencast\local\updatemetadata_form(null, array('metadata' => $metadata, 'metadata_catalog' => $metadata_catalog, 'courseid' => $courseid, 'identifier' => $identifier));

if ($updatemetadataform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $updatemetadataform->get_data()) {
    $new_metadata = [];
    $metadata_ids = array_column($metadata, 'id');
    foreach ($metadata_ids as $key) {
        if (array_key_exists($key, $data)) {
            $content_obj = [
                'id' => $key,
                'value' => $key == 'startDate' ? date('Y-m-d', $data->startDate) : $data->$key
            ];
            $new_metadata[] = $content_obj;
            if ($key == 'startDate') {
                $startTime = [
                    'id' => 'startTime',
                    'value' => date('H:i:sz', $data->startDate)
                ];
                $new_metadata[] = $startTime;
            }
        }
    }
    $msg = '';
    $res = $opencast->update_event_metadata($identifier, $new_metadata);
    if ($res) {
        $msg = get_string('updatemetadatasaved', 'block_opencast');
    }
    redirect($redirecturl, $msg);
}
$PAGE->requires->js_call_amd('block_opencast/block_form_handler','init');
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('updatemetadata', 'block_opencast'));
$updatemetadataform->display();
echo $OUTPUT->footer();
