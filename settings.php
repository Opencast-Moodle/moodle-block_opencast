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
 * Settings.
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading('block_opencast/apisettings',
        new lang_string('apisettings', 'block_opencast'), ''));

    $settings->add(new admin_setting_configtext('block_opencast/apiurl',
        new lang_string('apiurl', 'block_opencast'),
        new lang_string('apiurldesc', 'block_opencast'), 'moodle-proxy.rz.tu-ilmenau.de', PARAM_URL));

    $settings->add(new admin_setting_configtext('block_opencast/apiusername',
        new lang_string('apiusername', 'block_opencast'),
        new lang_string('apiusernamedesc', 'block_opencast'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('block_opencast/apipassword',
        new lang_string('apipassword', 'block_opencast'),
        new lang_string('apipassworddesc', 'block_opencast'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('block_opencast/connecttimeout',
        new lang_string('connecttimeout', 'block_opencast'),
        new lang_string('connecttimeoutdesc', 'block_opencast'), 1, PARAM_INT));

    $settings->add(new admin_setting_heading('block_opencast/cronsettings',
        new lang_string('cronsettings', 'block_opencast'), ''));

    $url = new moodle_url('/admin/tool/task/scheduledtasks.php');
    $link = html_writer::link($url, get_string('pluginname', 'tool_task'), array('target' => '_blank'));

    $settings->add(new admin_setting_configtext('block_opencast/limituploadjobs',
        new lang_string('limituploadjobs', 'block_opencast'),
        new lang_string('limituploadjobsdesc', 'block_opencast', $link), 1, PARAM_INT));

    $settings->add(new admin_setting_configtext('block_opencast/uploadworkflow',
        new lang_string('uploadworkflow', 'block_opencast'),
        new lang_string('uploadworkflowdesc', 'block_opencast'), 'ng-schedule-and-upload', PARAM_TEXT));

    $settings->add(new admin_setting_configcheckbox('block_opencast/publishtoengage',
        new lang_string('publishtoengage', 'block_opencast'),
        new lang_string('publishtoengagedesc', 'block_opencast'), 0));

    $settings->add(new admin_setting_configcheckbox('block_opencast/reuseexistingupload',
        new lang_string('reuseexistingupload', 'block_opencast'),
        new lang_string('reuseexistinguploaddesc', 'block_opencast'), 0));

    $settings->add(new admin_setting_heading('block_opencast/overviewsettings',
        new lang_string('overviewsettings', 'block_opencast'), ''));

    $settings->add(new admin_setting_configtext('block_opencast/limitvideos',
        new lang_string('limitvideos', 'block_opencast'),
        new lang_string('limitvideosdesc', 'block_opencast'), 5, PARAM_INT));

    $settings->add(new admin_setting_heading('block_opencast/accesspolicies',
        new lang_string('accesspolicies', 'block_opencast'), ''));

    $settings->add(new admin_setting_configcheckbox('block_opencast/group_creation',
        new lang_string('groupcreation', 'block_opencast'),
        new lang_string('groupcreationdesc', 'block_opencast'), 1));
}


