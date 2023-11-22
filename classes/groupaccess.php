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
 * Persistent table of groupaccess
 *
 * @package    block_opencast
 * @copyright  2018 Tobias Reischmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast;

use core\persistent;

/**
 * Persistent table of groupaccess
 *
 * @package    block_opencast
 * @copyright  2018 Tobias Reischmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupaccess extends persistent {


    /** Table name for the persistent. */
    const TABLE = 'block_opencast_groupaccess';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'id' => [
                'type' => PARAM_INT,
            ],
            'ocinstanceid' => [
                'type' => PARAM_INT,
            ],
            'opencasteventid' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'moodlegroups' => [
                'type' => PARAM_SEQUENCE,
            ],
        ];
    }
}
