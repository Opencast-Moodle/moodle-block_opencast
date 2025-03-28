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
 * @copyright  2017 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_opencast\admin_setting_configeditabletable;
use block_opencast\admin_setting_configtextvalidate;
use block_opencast\admin_setting_hiddenhelpbtn;
use block_opencast\local\ltimodulemanager;
use block_opencast\local\visibility_helper;
use tool_opencast\exception\opencast_api_response_exception;
use block_opencast\setting_helper;
// use block_opencast\setting_default_manager;
use core\notification;
use core_admin\local\settings\filesize;
use tool_opencast\empty_configuration_exception;
use tool_opencast\local\environment_util;
use tool_opencast\local\settings_api;

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // Needs this condition or there is error on login page.
    global $CFG, $PAGE, $ADMIN, $OUTPUT;

    // Empty $settings to prevent a single settings page from being created by lib/classes/plugininfo/block.php
    // because we will create several settings pages now.

    $settings = null;

    // Create admin settings category.
    $settingscategory = new admin_category('block_opencast', new lang_string('settings', 'block_opencast'));
    $ADMIN->add('blocksettings', $settingscategory);

    $ocinstances = settings_api::get_ocinstances();
    $multiocinstance = count($ocinstances) > 1;

    // Create empty settings page structure to make the site administration work on non-admin pages.
    if (!$ADMIN->fulltree) {

        foreach ($ocinstances as $instance) {
            if (count($ocinstances) > 1) {
                $instancecategory = new admin_category('block_opencast_instance_' . $instance->id, $instance->name);
                $ADMIN->add('block_opencast', $instancecategory);
                $category = 'block_opencast_instance_' . $instance->id;
            } else {
                $category = 'block_opencast';
            }

            // Setting page: General.
            $settingspage = new admin_settingpage('block_opencast_generalsettings_' . $instance->id,
                get_string('general_settings', 'block_opencast'));
            $ADMIN->add($category, $settingspage);
        }

        // Because we are using the calls to get workflows actively in the setting, therefore we need to narrow it down only
        // when needed. So we check if this setting page is currently requested.
    } else if ($ADMIN->fulltree &&
        (strpos($PAGE->pagetype, 'block_opencast') !== false || // When only landing on the admin settings page for block_opencast.
            ($PAGE->pagetype == 'admin-upgradesettings' && $PAGE->pagelayout == 'maintenance') || // During upgrade or install.
            (environment_util::is_cli_application() && !environment_util::is_moodle_plugin_ci_workflow()))) {

        foreach ($ocinstances as $instance) {
            if (count($ocinstances) > 1) {
                $instancecategory = new admin_category('block_opencast_instance_' . $instance->id, $instance->name);
                $ADMIN->add('block_opencast', $instancecategory);
                $category = 'block_opencast_instance_' . $instance->id;
            } else {
                $category = 'block_opencast';
            }

            // Setting page: General.
            $generalsettings = new admin_settingpage('block_opencast_generalsettings_' . $instance->id,
                get_string('general_settings', 'block_opencast'));
            $ADMIN->add($category, $generalsettings);


            $generalsettings->add(
                new admin_setting_configtext('block_opencast/limitvideos_' . $instance->id,
                    get_string('limitvideos', 'block_opencast'),
                    get_string('limitvideosdesc', 'block_opencast'), 5, PARAM_INT));


        }
    }
}
$settings = null;

// In order to be able to offer "Settings" links in Plugins overview and Manage blocks,
// we need to use a specific setting category (admin_category) that has to be "blocksettingopencast",
// therefore we provide it as a hidden subcategory to block_opencast to minimize the changes.
$blocksettingscategory = new admin_category('blocksettingopencast', new lang_string('settings_page', 'block_opencast'), true);
$mainsettingurl = new moodle_url('/admin/category.php', ['category' => 'block_opencast']);
$settingexternalpage = new admin_externalpage(
    'blocksettingopencast_externalpage',
    get_string('settings_page_url', 'block_opencast', get_string('settings', 'block_opencast')),
    $mainsettingurl
);
$blocksettingscategory->add('blocksettingopencast', $settingexternalpage);
$ADMIN->add('block_opencast', $blocksettingscategory);
