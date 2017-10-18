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
 * @author     Andreas Wagner
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/lib/filelib.php');

$bridge = \block_opencast\local\apibridge::get_instance();

// var_dump($bridge->ensure_acl_group_exists(5));
// var_dump($bridge->ensure_course_series_exists(5));

// $bridge->ensure_acl_group_assigned('b2b7faa0-2399-473b-b588-39e4da6b70d0', 2);
// var_dump($bridge->ensure_series_assigned('4fbb9e0a-be88-48ba-80d0-b3434532acdc', 'd9fe7efb-c137-4f5b-bc58-2e9fd6aefd2e'));
// var_dump($bridge->get_already_existing_event(array('6ee6106a-41ad-4965-877f-5bf18e3204b9')));

$uploadhelper = new \block_opencast\local\upload_helper();
$uploadhelper->cron();