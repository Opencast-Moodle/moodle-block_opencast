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
 * Test Type Helper to recognize whether the test is legacy or JWT or something else.
 * @package    block_opencast
 * @copyright  2026 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test Type Helper to recognize whether the test is legacy or JWT or something else.
 * @package    block_opencast
 * @copyright  2026 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_opencast_test_type_helper {
    /** @var string JWT test type. */
    public const JWT_TEST_TYPE = 'jwt';
    /** @var string Legacy test type. */
    public const LEGACY_TEST_TYPE = 'legacy';
    /** @var string Default test type. */
    public const DEFAULT_TEST_TYPE = self::LEGACY_TEST_TYPE;
    /** @var string Env variable name. */
    public const ENV_VAR_NAME = 'TEST_TYPE';

    /**
     * Checks if the test is a legacy by comparing the env param (with fallbacl to default)
     * @return bool it is a legacy test or not.
     */
    public static function is_legacy_test(): bool {
        $envtesttype = getenv(self::ENV_VAR_NAME);
        if (empty($envtesttype)) {
            $envtesttype = self::DEFAULT_TEST_TYPE;
        }
        return $envtesttype === self::LEGACY_TEST_TYPE;
    }

    /**
     * Checks if the test is a jwt by comparing the env param (with fallbacl to default)
     * @return bool it is a jwt test or not.
     */
    public static function is_jwt_test(): bool {
        $envtesttype = getenv(self::ENV_VAR_NAME);
        if (empty($envtesttype)) {
            $envtesttype = self::DEFAULT_TEST_TYPE;
        }
        return $envtesttype === self::JWT_TEST_TYPE;
    }
}
