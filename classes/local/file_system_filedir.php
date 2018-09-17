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
 * Subclass of the file_system_filedir class to delete one single file
 * from trashdir.
 *
 * @package   block_opencast
 * @copyright 2018 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/filestorage/file_system_filedir.php');

/**
 * Subclass of the file_system_filedir class to delete one single file
 * from trashdir.
 *
 * @package   block_opencast
 * @copyright 2018 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_system_filedir extends \file_system_filedir {

    /**
     * Unlink one file in the trashdir by contenthash
     *
     * @param string $contenthash
     * @return boolean true if succeeded
     */
    public function delete_file_from_trashdir($contenthash) {

        if (!$this->file_exists_in_trashdir($contenthash)) {
            return false;
        }

        $filepath = $this->get_trash_fullpath_from_hash($contenthash);
        return unlink($filepath);
    }

    /**
     * Check, if a file exists in trashdir.
     *
     * @param string $contenthash
     * @return boolean true, if the file exists in trashdir.
     */
    public function file_exists_in_trashdir($contenthash) {
        $filepath = $this->get_trash_fullpath_from_hash($contenthash);
        return file_exists($filepath);
    }

}