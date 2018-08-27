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
 * Test class for the block opencast.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

use block_opencast\local\file_deletionmanager;

/**
 * Test class for the block opencast.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_opencast_delete_file_testcase extends advanced_testcase {

    /**
     * Test how trash deletion works.
     */
    public function test_delete_files() {
        global $DB;

        // Set up.
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user(['lastname' => 'user1']);

        $contextid = 1;
        $component = 'block_opencast_test';
        $filearea = 'videotoupload';
        $itemid = 0;

        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => 'test'
        ];
        $fs->create_file_from_string($filerecord, 'test');

        // Check file is in mdl_files.
        $storedfiles = $DB->get_records('files', $filerecord);
        $this->assertEquals(1, count($storedfiles));
        $storedfile = reset($storedfiles);

        $filerecord['filename'] = 'test2';
        $fs->create_file_from_string($filerecord, 'test2');
        $storedfile2 = $DB->get_record('files', $filerecord);

        $draftid_editor = file_get_submitted_draft_itemid('video_filemanager');
        file_prepare_draft_area($draftid_editor, $contextid, $component, $filearea, $itemid);
        file_deletionmanager::track_draftitemid($contextid, $draftid_editor);

        // Check user draft files are created for this user.
        $userdraft = $DB->get_records('files', ['component' => 'user', 'contenthash' => $storedfile->contenthash]);
        $this->assertEquals(1, count($userdraft));

        $draftid_editor = file_get_submitted_draft_itemid('video_filemanager');
        file_prepare_draft_area($draftid_editor, $contextid, $component, $filearea, $itemid);
        file_deletionmanager::track_draftitemid($contextid, $draftid_editor);

        // Check user draft files are created for this user.
        $userdraft = $DB->get_records('files', ['component' => 'user', 'contenthash' => $storedfile->contenthash]);
        $this->assertEquals(2, count($userdraft));

        // Delete file,
        $fs->delete_area_files(1, 'block_opencast_test');

        // Confirm delete,
        $count = $DB->count_records('files', $filerecord);
        $this->assertEquals(0, $count);

        $draftid_editor = file_get_submitted_draft_itemid('video_filemanager');
        file_prepare_draft_area($draftid_editor, $contextid, $component, $filearea, $itemid);
        file_deletionmanager::track_draftitemid($contextid, $draftid_editor);

        // Check trashdir.
        $filedir = new \block_opencast\local\file_system_filedir();
        $contenthash = $storedfile->contenthash;

        // File may not be in trash, because user draft entry exists.
        $exists = $filedir->file_exists_in_trashdir($contenthash);
        $this->assertFalse($exists);

        // Delete user draft.
        file_deletionmanager::delete_draft_files_by_source($contenthash, $contextid);

        // File muset be in trash. All draft should have been deleted.
        $exists = $filedir->file_exists_in_trashdir($contenthash);
        $this->assertTrue($exists);

        // Delete file from trash.
        $filedir->delete_file_from_trashdir($contenthash);

        $exists = $filedir->file_exists_in_trashdir($contenthash);
        $this->assertFalse($exists);

        $contenthash2 = $storedfile2->contenthash;

        // Check user draft files are created for this user.
        $userdraft = $DB->get_records('files', ['component' => 'user', 'contenthash' => $storedfile2->contenthash]);
        $this->assertEquals(2, count($userdraft));

        $filerecord['filename'] = 'test2';
        $fs->create_file_from_string($filerecord, 'test2');
        $storedfile = $DB->get_record('files', $filerecord);

        $this->setUser($user1);
        $draftid_editor = file_get_submitted_draft_itemid('video_filemanager');
        file_prepare_draft_area($draftid_editor, $contextid, $component, $filearea, $itemid);
        file_deletionmanager::track_draftitemid($contextid, $draftid_editor);

        // Check user draft files are created for this user.
        $userdraft = $DB->get_records('files', ['component' => 'user', 'contenthash' => $storedfile2->contenthash]);
        $this->assertEquals(3, count($userdraft));

        // Delete user draft.
        file_deletionmanager::delete_draft_files_by_source($contenthash2, $contextid);
        $contenthash2 = $storedfile2->contenthash;

        $exists = $filedir->file_exists_in_trashdir($contenthash2);
        $this->assertFalse($exists);

        $storedfiles = $DB->get_records('files', $filerecord);
        $this->assertEquals(1, count($storedfiles));

        // Delete file,
        $fs->delete_area_files(1, 'block_opencast_test');

        $exists = $filedir->file_exists_in_trashdir($contenthash2);
        $this->assertTrue($exists);

        $filedir->delete_file_from_trashdir($contenthash2);

        $exists = $filedir->file_exists_in_trashdir($contenthash2);
        $this->assertFalse($exists);
    }
}
