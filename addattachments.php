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
 * @copyright  2020 Tim Schroeder, RWTH Aachen University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use block_opencast\local\upload_helper;
use tool_opencast\local\api;

global $PAGE, $OUTPUT, $CFG;

require_once($CFG->dirroot . '/repository/lib.php');

$identifier = required_param('video_identifier', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/opencast/addattachments.php',  array('video_identifier' => $identifier, 'courseid' => $courseid));
$PAGE->set_url($baseurl);

$redirecturl = new moodle_url('/blocks/opencast/index.php', array('courseid' => $courseid));

require_login($courseid, false);

$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_opencast'));
$PAGE->set_heading(get_string('pluginname', 'block_opencast'));
$PAGE->navbar->add(get_string('pluginname', 'block_opencast'), $redirecturl);
$PAGE->navbar->add(get_string('addattachments', 'block_opencast'), $baseurl);

// Capability check.
$coursecontext = context_course::instance($courseid);
require_capability('block/opencast:addvideo', $coursecontext);

$opencast = \block_opencast\local\apibridge::get_instance();
$metadata_catalog = upload_helper::get_opencast_metadata_catalog();

$addattachmentsform = new \block_opencast\local\addattachments_form(null, ['metadata_catalog' => $metadata_catalog, 'courseid' => $courseid, 'identifier' => $identifier]);

if ($addattachmentsform->is_cancelled()) {
    redirect($redirecturl);
}

if ($data = $addattachmentsform->get_data()) {

    // Upload attachments.
    $messages = [];
    $api = new api();
    foreach ($metadata_catalog as $field) {
        if ($field->datatype != 'filepicker') {
            continue;
        }
        $id = $field->name;
        if ($data->$id) {
            $storedfile_attachment = $addattachmentsform->save_stored_file($id, $coursecontext->id, 'block_opencast', upload_helper::OC_FILEAREA, $data->$id);
            if ($storedfile_attachment) {
                \block_opencast\local\file_deletionmanager::track_draftitemid($coursecontext->id, $data->$id);
                if ($storedfile_attachment === false) {
                    throw new \moodle_exception('attachmentmissingfile', 'tool_opencast');
                }

                $params = json_decode($field->param_json);
                $flavor = $params->flavor;
                if (empty($flavor)) {
                    throw new \moodle_exception('attachmentmissingflavor', 'tool_opencast');
                }
                list($flavorType, $flavorSubType) = explode('/', $flavor);
                if (empty($flavorType) || empty($flavorSubType)) {
                    throw new \moodle_exception('attachmentinvalidflavor', 'tool_opencast', '', $flavor);
                }
                // TODO move this stuff to upload_helper?
                $metadata = new \stdClass();
                $metadata->assets = new \stdClass();
                $metadata->assets->options = [];
                $metadata->assets->options[] = new \stdClass();
                $metadata->assets->options[0]->id = 'attachment_captions_webvtt'; // TODO
                $metadata->assets->options[0]->type = 'attachment';
                $metadata->assets->options[0]->flavorType = $flavorType;
                $metadata->assets->options[0]->flavorSubType = $flavorSubType;
                $metadata->assets->options[0]->displayOrder = 1;
                $metadata->assets->options[0]->title = 'EVENTS.EVENTS.NEW.UPLOAD_ASSET.OPTION.CAPTIONS_WEBVTT'; // TODO
                $metadata->processing = new stdClass();
                $metadata->processing->workflow = 'publish-uploaded-assets';
                $metadata->processing->configuration = new \stdClass();
                $metadata->processing->configuration->downloadSourceflavorsExist = 'true';
                $fieldname = 'download-source-flavors';
                $metadata->processing->configuration->$fieldname = $flavor;
                $metadatajson = json_encode($metadata);

                $res = $api->oc_post("/admin-ng/event/$identifier/assets", [
                    "attachment_captions_webvtt.0" => $storedfile_attachment,
                    "metadata" => $metadatajson
                ]);
                if ($api->get_http_code() >= 400) {
                    throw new \moodle_exception('serverconnectionerror', 'tool_opencast');
                }

                if ($res) {
                    $messages[] = "\n</br>\n".get_string('updateattachmentsaved', 'block_opencast', '', $id);
                }
                // TODO can we just upload multiple attachments at the same time? or do we need to
                // restrict the user to only upload one attachment at a time?
            }
        }
    }

    $msg = implode("\n</br>\n", $messages);
    redirect($redirecturl, $msg);
}
$PAGE->requires->js_call_amd('block_opencast/block_form_handler','init');
$renderer = $PAGE->get_renderer('block_opencast');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addattachments', 'block_opencast'));
$addattachmentsform->display();
echo $OUTPUT->footer();
