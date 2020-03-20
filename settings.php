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
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // Needs this condition or there is error on login page.

    $settingscategory = new admin_category('blockopencastfolder',
        new lang_string('settings', 'block_opencast'));

    $ADMIN->add('blocksettings', $settingscategory);

    $ADMIN->add('blockopencastfolder', new admin_externalpage('block_opencast',
        get_string('general_settings', 'block_opencast'),
        new moodle_url('/blocks/opencast/adminsettings.php')));

    $additionalsettings = new admin_settingpage('additionalsettings',
        get_string('additional_settings', 'block_opencast'));

    $ADMIN->add('blockopencastfolder', $additionalsettings);

    $additionalsettings->add(
        new admin_setting_configcheckbox('block_opencast/enable_opencast_studio_link',
            get_string('enableopencaststudiolink', 'block_opencast'),
            get_string('enableopencaststudiolink_desc', 'block_opencast'), 0));

    $additionalsettings->add(
        new admin_setting_configtext('block_opencast/lticonsumerkey',
            get_string('lticonsumerkey', 'block_opencast'),
            get_string('lticonsumerkey_desc', 'block_opencast'), ""));

    $additionalsettings->add(
        new admin_setting_configpasswordunmask('block_opencast/lticonsumersecret',
            get_string('lticonsumersecret', 'block_opencast'),
            get_string('lticonsumersecret_desc', 'block_opencast'), ""));


}
$settings = null;