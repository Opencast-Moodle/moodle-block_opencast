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
 * @package block_opencast
 * @group block_opencast
 * @copyright  2018 Tobias Reischmann, WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once('helper/apibridge_testable.php');

class block_opencast_roles_testcase extends advanced_testcase {

    /**
     * Tests if the list of comma separated actions is returned correctly.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_roles() {

        $this->resetAfterTest();

        $bridge = new block_opencast_apibridge_testable();
        $generator = $this->getDataGenerator()->get_plugin_generator('block_opencast');
        $generator->create_aclroles();

        $roles = $bridge->getroles_testable();

        self::assertCount(4, $roles, 'More roles present as expected.');

        $expected = [
            'ROLE_ADMIN' => ['write', 'read'],
            'ROLE_GROUP_MOODLE_COURSE_[COURSEID]' => ['read'],
            'testrole1' => ['action1'],
            'testrole2' => ['action1', 'action2'],
            ];

        foreach ($expected as $expectedrole => $expectedactions) {
            $foundrole = false;
            foreach ($roles as $role) {
                if ($role->rolename === $expectedrole) {
                    $foundrole = true;
                    $foundaction = false;
                    foreach ($expectedactions as $expectedaction) {
                        foreach ($role->actions as $action) {
                            if ($action === $expectedaction) {
                                $foundaction = true;
                            }
                        }
                    }
                    self::assertTrue($foundaction, 'action ' . $expectedaction . ' of role ' .
                        $expectedrole . ' was not present.');
                }
            }
            self::assertTrue($foundrole, 'role ' . $expectedrole . ' was not present.');
        }
    }

}


