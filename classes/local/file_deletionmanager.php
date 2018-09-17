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
 * Manager to enable ad hoc file deletion
 *
 * @package   block_opencast
 * @copyright 2018 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager to enable ad hoc file deletion
 *
 * @package   block_opencast
 * @copyright 2018 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_deletionmanager extends \file_system_filedir {

    /**
     * Delete all the users draft file entries that belongs to a videofile within
     * the same course.
     *
     * Note that we take care about the course context and do not delete files
     * entries from other courses.
     *
     * @param string $contenthash
     * @param int $coursecontextid
     * @param int $itemid
     */
    public static function delete_draft_files_by_source($contenthash, $coursecontextid, $itemid = 0) {
        global $DB;

        $params = [
            'component' => 'user',
            'filearea' => 'draft',
            'contenthash' => $contenthash,
            'filename' => '.',
            'contextid' => $coursecontextid
        ];

        // Get draft entries belonging to stored file.
        $sql = "SELECT f.*
                FROM {files} f
                JOIN {block_opencast_draftitemid} d ON f.itemid = d.itemid AND d.contextid = :contextid
                WHERE f.contenthash = :contenthash AND f.component = :component
                AND f.filearea = :filearea AND f.filename <> :filename";

        if (!empty($itemid)) {
            $params['itemid'] = $itemid;
            $sql .= " AND f.itemid = :itemid ";
        }

        if (!$draftfiles = $DB->get_records_sql($sql, $params)) {
            return;
        }

        $fs = get_file_storage();
        foreach ($draftfiles as $draftfile) {
            $fs->get_file_instance($draftfile)->delete();
        }
    }

    /**
     * Store the draft item id of filemanager and the course context id to remember
     * in which context the filemanage was used. This is needed to restrict the
     * deletion of draft file after a video was uploaded to opencast.
     *
     * @param int $coursecontextid
     * @param int $itemid
     */
    public static function track_draftitemid($coursecontextid, $itemid) {
        global $DB;

        // Do some cleanup.
        self::cleanup_old_draftitemids();

        $params = [
            'contextid' => $coursecontextid,
            'itemid' => $itemid
        ];

        // Moodle generates a new itemid for filemanager, so we know that $itemid
        // is currently unused and we keep only one entry for the current filemanager.
        $exists = $DB->get_records('block_opencast_draftitemid', $params);
        foreach ($exists as $exist) {
            $DB->delete_records('block_opencast_draftitemid', ['id' => $exist->id]);
        }

        // Insert the data.
        $record = (object) [
            'contextid' => $coursecontextid,
            'itemid' => $itemid,
            'timecreated' => time()
        ];
        $DB->insert_record('block_opencast_draftitemid', $record);
    }

    /**
     * Delete track of draftitemids after 5 days.
     *
     * It must be noted, that draft entries should have been removed within
     * 4 days by filestorage cron, so we can do so some save cleanup here.
     */
    public static function cleanup_old_draftitemids() {
        global $DB;

        $old = time() - 5 * DAYSECS;
        $DB->delete_records_select('block_opencast_draftitemid', 'timecreated < ?', [$old]);
    }

    /**
     * Fully delete a file, which means that we remove user draft entries from files table
     * for itemids known used in context of the file (which is the course context).
     *
     * Please note that the file will be removed even if user is uploading the same file in
     * the moment the cronjob has finished to transfer the file to opencast.
     *
     * We are aware of this, but according to the use cast this is done intentional (file
     * must not be transferred again as it is already on opencast server.
     *
     * @param \stored_file $storedfile
     */
    public static function fulldelete_file($storedfile) {

        self::delete_draft_files_by_source($storedfile->get_contenthash(), $storedfile->get_contextid());

        $filedir = new file_system_filedir();
        $filedir->delete_file_from_trashdir($storedfile->get_contenthash());
    }

}