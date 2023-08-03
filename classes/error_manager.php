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
 * Manager for block_opencast errors.
 *
 * @package    block_opencast
 * @copyright  2023 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

/**
 * Manager for block_opencast errors.
 *
 * @package    block_opencast
 * @copyright  2023 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class error_manager {

    const DELETE_COURSE_COULD_NOT_RETRIEVE_SERIES = 1;
    const DELETE_COURSE_COULD_NOT_DELETE_EVENT = 2;


    public static function add_error(int $errorkind, string $errortext, array|null $extradata): void {
        global $DB;
        $record = new \stdClass();
        $record->errorkind = $errorkind;
        $record->errortext = $errortext;
        $record->extrajson = $extradata ? json_encode($extradata) : null;
        $record->timecreated = time();
        $DB->insert_record('block_opencast_errors', $record);
    }

}
