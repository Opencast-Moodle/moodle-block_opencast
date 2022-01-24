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
 * Scheduled task to clean up Opencast Video Provider/LTI episode modules after a video import
 *
 * @package    block_opencast
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_opencast\task;

use block_opencast\local\activitymodulemanager;
use block_opencast\local\apibridge;
use block_opencast\local\ltimodulemanager;

class cleanup_imported_episodes_cron extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     * @return string
     */
    public function get_name() {
        return get_string('processepisodecleanup', 'block_opencast');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $DB;

        $ocinstances = json_decode(get_config('tool_opencast', 'ocinstances'));
        foreach ($ocinstances as $ocinstance) {

            // If the import feature is disabled but the scheduled task is not, we are already done.
            if (get_config('block_opencast', 'importvideosenabled_' . $ocinstance->id) != true) {
                mtrace("...importing videos is disabled for opencast instance {$ocinstance->id}, so nothing to do.");
                continue;
            }

            // Get all workflows which are scheduled to be handled.
            $workflowsql = 'SELECT DISTINCT ocworkflowid, queuecount, timecreated, timemodified ' .
                'FROM {block_opencast_ltiepisode_cu} WHERE ocinstanceid = :ocinstanceid';
            $workflows = $DB->get_records_sql($workflowsql, array('ocinstanceid' => $ocinstance->id));

            // If there aren't any jobs, we are done already.
            if (count($workflows) < 1) {
                mtrace("...no cleanup jobs for opencast instance {$ocinstance->id} are scheduled currently, so nothing to do.");
                continue;
            }

            // Iterate over workflows.
            foreach ($workflows as $workflow) {
                mtrace('Proceed: OC workflow ' . $workflow->ocworkflowid);

                // Decide if we really want to proceed this workflow now.
                // If the workflow has been postponed 2 times, which means that we tried it 3 times in a row
                // and which means that, in normal setups, 3 minutes have passed since the job was scheduled.
                if ($workflow->queuecount > 2) {
                    // Then we want to wait some more time until the next try to avoid to bother Opencast too much.
                    // Before making the 4th try, we will wait 3 minutes instead of just 1 minute.
                    // Then we wait 6 minutes, then 12, then 24, then 48 and finally keep waiting 96 minutes before every try.
                    $waitminutes = min(3 * (2 ** ($workflow->queuecount - 3)), 96);
                    $waituntil = $workflow->timemodified + ($waitminutes * MINSECS);

                    // If we haven't waited long enough.
                    if ($waituntil > time()) {
                        mtrace('  Cleanup job(s) for OC workflow ' . $workflow->ocworkflowid .
                            ' were skipped as OC hasn\'t had a result for us yet and we don\'t want to bother it that much. ' .
                            'Next try will be at ' .
                            date_format_string($waituntil, get_string('strftimedatetime', 'langconfig')));
                        continue;
                    }

                    // But in the end, if Opencast did not give us an episode id for 5 days, nobody will be waiting anymore for this
                    // course module update and this job should be deleted.
                    if ($workflow->timecreated + (5 * DAYSECS) < time()) {
                        // Remove the cleanup job.
                        $DB->delete_records('block_opencast_ltiepisode_cu',
                            array('ocworkflowid' => $workflow->ocworkflowid, 'ocinstanceid' => $ocinstance->id));
                        mtrace('  Cleanup job(s) for workflow ' . $workflow->ocworkflowid .
                            ' were removed as we have waited for 5 days without success to get the duplicated episode ID from OC.');
                        // TODO: Send a notification to the admin in this case.
                        continue;
                    }
                }

                try {
                    $apibridge = apibridge::get_instance($ocinstance->id);
                    $episodeid = $apibridge->get_duplicated_episodeid($workflow->ocworkflowid);

                    // If we have no chance to get an episode ID - not now and not if we postpone the job.
                    // (See function get_duplicated_episodeid() in apibridge for details when this can happen).
                    if ($episodeid === false) {
                        // Remove the cleanup job.
                        $DB->delete_records('block_opencast_ltiepisode_cu',
                            array('ocworkflowid' => $workflow->ocworkflowid, 'ocinstanceid' => $ocinstance->id));
                        mtrace('  Cleanup job(s) for workflow ' . $workflow->ocworkflowid . ' ' .
                            'were removed as the stored OC workflow does not exist or does and will ' .
                            'not hold a duplicated episode ID.');
                        continue;
                    }

                    // If the OC workflow exists but if there isn't an episode ID (yet).
                    if ($episodeid === '') {
                        // Postpone the cleanup job.
                        $params = array('increment' => 1, 'time' => time(), 'ocworkflowid' => $workflow->ocworkflowid,
                            'ocinstanceid' => $ocinstance->id);
                        $DB->execute('UPDATE {block_opencast_ltiepisode_cu} ' .
                            'SET queuecount = queuecount + :increment, timemodified = :time ' .
                            'WHERE ocworkflowid = :ocworkflowid AND ocinstanceid = :ocinstanceid',
                            $params);
                        mtrace('  Cleanup job(s) for OC workflow ' . $workflow->ocworkflowid .
                            ' were postponed as the stored OC workflow does not hold a duplicated episode ID (yet)');
                        continue;
                    }

                    // Get all course modules which relate to the given workflow.
                    $coursemodules = $DB->get_fieldset_select('block_opencast_ltiepisode_cu', 'cmid',
                        'ocworkflowid = :ocworkflowid AND ocinstanceid = :ocinstanceid',
                        array('ocworkflowid' => $workflow->ocworkflowid, 'ocinstanceid' => $ocinstance->id));

                    // Get the course where these course modules are located.
                    // Here, we assume that even if we have to handle multiple course modules for this job, all belong to the same
                    // course. This assumption is ok as one workflow can just be triggered from within one single course.
                    $courseid = $DB->get_field('block_opencast_ltiepisode_cu', 'courseid',
                        array('ocworkflowid' => $workflow->ocworkflowid, 'ocinstanceid' => $ocinstance->id), IGNORE_MULTIPLE);

                    // Split modules into LTI and activity modules to handle them respectively.
                    $ltimodules = array();
                    $activities = array();
                    foreach ($coursemodules as $module) {
                        if (get_fast_modinfo($courseid)->get_cm($module)->modname == 'opencast') {
                            $activities[] = $module;
                        } else {
                            $ltimodules[] = $module;
                        }
                    }

                    // Let the LTI Module manager cleanup these episodes.
                    $cleanupresult = ltimodulemanager::cleanup_episode_modules($ocinstance->id,
                        $courseid, $ltimodules, $episodeid);

                    // Cleanup the activity episode modules.
                    $cleanupresult = $cleanupresult &&
                        activitymodulemanager::cleanup_episode_modules($courseid, $activities, $episodeid);

                    // If something with the cleanup failed.
                    if ($cleanupresult != true) {
                        // Remove the cleanup job.
                        $DB->delete_records('block_opencast_ltiepisode_cu',
                            array('ocworkflowid' => $workflow->ocworkflowid, 'ocinstanceid' => $ocinstance->id));
                        mtrace('  Cleanup job(s) for workflow ' . $workflow->ocworkflowid .
                            ' failed during the update of the episode activities and were removed. There won\'t be a retry.');
                        // TODO: Send a notification to the admin in this case.
                        continue;

                        // Otherwise.
                    } else {
                        // Remove the cleanup job.
                        $DB->delete_records('block_opencast_ltiepisode_cu',
                            array('ocworkflowid' => $workflow->ocworkflowid, 'ocinstanceid' => $ocinstance->id));
                        mtrace('  Cleanup job(s) for workflow ' . $workflow->ocworkflowid . ' finished successfully.');
                        continue;
                    }
                } catch (\moodle_exception $e) {
                    mtrace('  Cleanup job(s) failed with an exception: ' . $e->getMessage());
                    // Remove the cleanup job.
                    $DB->delete_records('block_opencast_ltiepisode_cu',
                        array('ocworkflowid' => $workflow->ocworkflowid, 'ocinstanceid' => $ocinstance->id));
                    mtrace('  Cleanup job(s) were removed. There won\'t be a retry.');
                    // TODO: Send a notification to the admin in this case.
                    continue;
                }
            }

        }

    }
}
