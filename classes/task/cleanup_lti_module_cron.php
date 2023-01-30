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
 * Scheduled task to clean up Opencast Video LTI episode modules
 *
 * @package    block_opencast
 * @copyright  2023 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\task;

use block_opencast\local\apibridge;
use block_opencast\local\ltimodulemanager;
use tool_opencast\local\settings_api;

/**
 * Scheduled task to clean up Opencast Video LTI episode modules
 *
 * @package    block_opencast
 * @copyright  2023 Farbod Zamani Boroujeni, ELAN e.V.
 * @author     Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_lti_module_cron extends \core\task\scheduled_task {

    /**
     * Get the task name.
     * @retunr string description.
     */
    public function get_name() {
        return get_string('processltimodulecleanup', 'block_opencast');
    }

    /**
     * Execute the task.
     * @throws moodle_exception upon which the task will be teminated.
     */
    public function execute() {
        try {
            // 1. Sync manually added LTI modules and add their enterie.
            // This step is necessary to be performed first because the next step of cleaning up will take care of faulty entries.
            mtrace('Step 1: Looking for manually added LTI modules...');
            $unrecordedmodulesnum = ltimodulemanager::record_manually_added_lti_modules();
            mtrace("... ($unrecordedmodulesnum) modules found and captured. ");

            // 2. Evaluate and cleanup the existing lti module entries.
            // This step is to check the validity of the existing lti module entries.
            mtrace('Step 2: Cleaning up LTI modules...');
            $deletemodulesnum = ltimodulemanager::cleanup_lti_module_entries();
            mtrace("... ($deletemodulesnum) modules deleted during cleanup process.");
        } catch (\moodle_exception $e) {
            mtrace('... cleanup process failed:');
            mtrace($e->getMessage());
        }
    }
}
