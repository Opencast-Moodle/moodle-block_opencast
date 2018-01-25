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

defined('MOODLE_INTERNAL') || die();

class block_opencast_apibridge_testable extends \block_opencast\local\apibridge {

    /**
     * For basic testcases connection parameters are not necessary.
     * block_opencast_apibridge_testable constructor.
     */
    public function __construct() {

    }

    /**
     * Test access for the protected getroles function.
     * @return array
     * @throws dml_exception
     */
    public function getroles_testable() {
        return parent::getroles();
    }
}