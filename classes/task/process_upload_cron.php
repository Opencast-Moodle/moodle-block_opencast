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
 * Task for processing the upload jobs.
 * @package   block_opencast
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\task;

use block_opencast\local\upload_helper;
use coding_exception;
use core\task\scheduled_task;
use dml_exception;
use lang_string;

/**
 * Task for processing the upload jobs.
 * @package block_opencast
 */
class process_upload_cron extends scheduled_task {


    /**
     * Get the name of the task.
     * @return lang_string|string
     * @throws coding_exception
     */
    public function get_name() {
        return get_string('processupload', 'block_opencast');
    }

    /**
     * Executes the task.
     * @throws dml_exception
     */
    public function execute() {
        $ulpoadhelper = new upload_helper();
        $ulpoadhelper->cron();
    }
}
