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
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class block_opencast_generator extends testing_block_generator {

    public function create_file($record = null) {
        global $USER;

        if (!isset($record['courseid'])) {
            print_error('course id missing');
        }

        $record['contextid'] = context_course::instance($record['courseid'])->id;
        $record['component'] = 'block_opencast';

        $record['filearea'] = \block_opencast\local\upload_helper::OC_FILEAREA;
        $record['itemid'] = 0;

        if (!isset($record['filepath'])) {
            $record['filepath'] = '/';
        }

        if (!isset($record['filename'])) {
            $record['filename'] = 'test.mp4';
        }

        if (!isset($record['userid'])) {
            $record['userid'] = $USER->id;
        }

        $record['source'] = 'Copyright stuff';

        if (!isset($record['author'])) {
            $record['author'] = fullname($USER);
        }

        if (!isset($record['license'])) {
            $record['license'] = 'cc';
        }

        if (!isset($record['filecontent'])) {
            print_error('file is missing');
        }

        $fs = get_file_storage();
        return $fs->create_file_from_string($record, $record['filecontent']);
    }

    /**
     * Adds two dummy roles with actions to the database.
     * @throws dml_exception
     */
    public function create_aclroles() {
        global $DB;

        $record = new \stdClass();
        $record->rolename = 'testrole1';
        $record->actions = 'action1';

        // Insert new record.
        $DB->insert_record('block_opencast_roles', $record, false);

        $record->rolename = 'testrole2';
        $record->actions = ' action1, action2 ';

        // Insert new record.
        $DB->insert_record('block_opencast_roles', $record, false);
    }

}
