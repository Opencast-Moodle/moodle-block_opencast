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
 * Video live update Helper.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

use html_writer;

/**
 * Video live update Helper.
 * @package    block_opencast
 * @copyright  2022 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class liveupdate_helper {

    /**
     * Returns the processing state live update hidden input flag.
     *
     * @param string $identifier video identifier
     * @param string $eventtitle video title
     * @return string a hidden input as processing state item
     */
    public static function get_liveupdate_processing_hidden_input($identifier, $eventtitle) {
        $attributes = ['type' => 'hidden', 'name' => 'liveupdate_processing_item', 'value' => $identifier];
        if (!empty($eventtitle)) {
            $attributes['data-title'] = $eventtitle;
        }
        return html_writer::empty_tag('input', $attributes);
    }

    /**
     * Returns the uploading job live update hidden input flag.
     *
     * @param string $jobid job identifier
     * @param string $jobtitle job title
     * @return string a hidden input for uploading job item
     */
    public static function get_liveupdate_uploading_hidden_input($jobid, $jobtitle) {
        $attributes = ['type' => 'hidden', 'name' => 'liveupdate_uploading_item', 'value' => $jobid];
        if (!empty($jobtitle)) {
            $attributes['data-title'] = $jobtitle;
        }
        return html_writer::empty_tag('input', $attributes);
    }

    /**
     * Evaluates and returns the processing state info.
     * It uses the block_opencast renderer to render processing state icon
     *
     * @param string $ocinstanceid opencast instance id
     * @param string $identifier event identifier
     * @return array|string $info the live update info or empty string if error happens.
     */
    public static function get_processing_state_info($ocinstanceid, $identifier) {
        global $PAGE;
        /** @var block_opencast_renderer $renderer */
        $renderer = $PAGE->get_renderer('block_opencast');
        $apibridge = apibridge::get_instance($ocinstanceid);
        // Get video object.
        $eventobject = $apibridge->get_opencast_video($identifier, true);
        // If there is an error, we return empty string to remove the live update item.
        if ($eventobject->error) {
            return '';
        }
        $video = $eventobject->video;
        // Get the processing state icon from renderer.
        $icon = $renderer->render_processing_state_icon($video->processing_state);
        $info['replace'] = $icon;
        // We pass the remove flag if the processing state is succeeded or failed,
        // to remove the item from live update.
        $remove = false;
        if ($video->processing_state == 'SUCCEEDED' || $video->processing_state == 'FAILED') {
            $remove = true;
        }
        $info['remove'] = $remove;

        // Finally, we return the info array containing an replacement element as well as remove flag.
        return $info;
    }

    /**
     * Get the live update info for uploading status.
     * It uses the block_opencast renderer to render the uploading status.
     *
     * @param int $uploadjobid the id of upload job
     * @return array|string $info the live update info or empty string if error happens.
     */
    public static function get_uploading_info($uploadjobid) {
        global $DB, $PAGE;

        // Get single upload job record to extract its current info.
        $sql = "SELECT status, countfailed FROM {block_opencast_uploadjob} " .
            "WHERE id = :uploadjobid";
        $params = [
            'uploadjobid' => $uploadjobid,
        ];
        $uploadjob = $DB->get_record_sql($sql, $params);
        // If something went wrong, we return empty string to remove the live update item.
        if (empty($uploadjob) || !property_exists($uploadjob, 'status')) {
            return '';
        }
        /** @var block_opencast_renderer $renderer */
        $renderer = $PAGE->get_renderer('block_opencast');
        // Get the status of uploading process from the renderer.
        $status = $renderer->render_status($uploadjob->status, $uploadjob->countfailed);
        $info['replace'] = $status;
        // We pass remove param, to decide whether to continue checking that item or not.
        $remove = false;
        if ($uploadjob->status == upload_helper::STATUS_TRANSFERRED) {
            // We remove the item from live update when the upload is transferred.
            $remove = true;
        }
        $info['remove'] = $remove;

        // Finally, we return the live update info.
        return $info;
    }
}
