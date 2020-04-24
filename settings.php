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
    global $CFG;

    $settingscategory = new admin_category('block_opencast_folder',
        new lang_string('settings', 'block_opencast'));

    $ADMIN->add('blocksettings', $settingscategory);

    $ADMIN->add('block_opencast_folder', new admin_externalpage('block_opencast',
        get_string('general_settings', 'block_opencast'),
        new moodle_url('/blocks/opencast/adminsettings.php')));

    $appearancesettings = new admin_settingpage('block_opencast_appearancesettings',
            get_string('appearance_settings', 'block_opencast'));

    $ADMIN->add('block_opencast_folder', $appearancesettings);

    $appearancesettings->add(
            new admin_setting_heading('block_opencast/appearance_overview',
                    get_string('appearance_overview_settingheader', 'block_opencast'),
                    ''));

    $appearancesettings->add(
            new admin_setting_configcheckbox('block_opencast/showpublicationchannels',
                    get_string('appearance_overview_settingshowpublicationchannels', 'block_opencast'),
                    get_string('appearance_overview_settingshowpublicationchannels_desc', 'block_opencast'), 1));

    $appearancesettings->add(
            new admin_setting_configcheckbox('block_opencast/showenddate',
                    get_string('appearance_overview_settingshowenddate', 'block_opencast'),
                    get_string('appearance_overview_settingshowenddate_desc', 'block_opencast'), 1));

    $appearancesettings->add(
            new admin_setting_configcheckbox('block_opencast/showlocation',
                    get_string('appearance_overview_settingshowlocation', 'block_opencast'),
                    get_string('appearance_overview_settingshowlocation_desc', 'block_opencast'), 1));

    $additionalsettings = new admin_settingpage('block_opencast_additionalsettings',
        get_string('additional_settings', 'block_opencast'));

    $ADMIN->add('block_opencast_folder', $additionalsettings);

    $additionalsettings->add(
        new admin_setting_heading('block_opencast/opencast_studio',
            get_string('opencaststudiointegration', 'block_opencast'),
            ''));

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

    // Control ACL section.
    $additionalsettings->add(
            new admin_setting_heading('block_opencast/acl_settingheader',
                    get_string('acl_settingheader', 'block_opencast'),
                    ''));

    // Control ACL: Enable feature.
    $additionalsettings->add(
            new admin_setting_configcheckbox('block_opencast/aclcontrolafter',
                    get_string('acl_settingcontrolafter', 'block_opencast'),
                    get_string('acl_settingcontrolafter_desc', 'block_opencast'), 1));

    // Control ACL: Enable group restriction.
    $additionalsettings->add(
            new admin_setting_configcheckbox('block_opencast/aclcontrolgroup',
                    get_string('acl_settingcontrolgroup', 'block_opencast'),
                    get_string('acl_settingcontrolgroup_desc', 'block_opencast'), 1));
    if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
        $additionalsettings->hide_if('block_opencast/aclcontrolgroup',
                'block_opencast/aclcontrolafter', 'notchecked');
    }

    // Add LTI module section.
    $additionalsettings->add(
        new admin_setting_heading('block_opencast/addlti_settingheader',
            get_string('addlti_settingheader', 'block_opencast'),
            ''));

    // Add LTI module: Enable feature.
    $additionalsettings->add(
        new admin_setting_configcheckbox('block_opencast/addltienabled',
            get_string('addlti_settingenabled', 'block_opencast'),
            get_string('addlti_settingenabled_desc', 'block_opencast'), 0));

    // Add LTI module: Default LTI module title.
    $additionalsettings->add(
        new admin_setting_configtext('block_opencast/addltidefaulttitle',
            get_string('addlti_settingdefaulttitle', 'block_opencast'),
            get_string('addlti_settingdefaulttitle_desc', 'block_opencast'),
            get_string('addlti_defaulttitle', 'block_opencast'),
            PARAM_TEXT));
    if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
        $additionalsettings->hide_if('block_opencast/addltidefaulttitle',
                'block_opencast/addltienabled', 'notchecked');
    }

    // Add LTI module: Preconfigured LTI tool.
    $tools = \block_opencast\local\ltimodulemanager::get_preconfigured_tools();
    // If there are any tools to be selected.
    if (count($tools) > 0) {
        $additionalsettings->add(
            new admin_setting_configselect('block_opencast/addltipreconfiguredtool',
                get_string('addlti_settingpreconfiguredtool', 'block_opencast'),
                get_string('addlti_settingpreconfiguredtool_desc', 'block_opencast'),
                null,
                $tools));

        // If there aren't any preconfigured tools to be selected.
    } else {
        // Add an empty element to at least create the setting when the plugin is installed.
        // Additionally, show some information text where to add preconfigured tools.
        $url = '/admin/settings.php?section=modsettinglti';
        $link = html_writer::link($url, get_string('manage_tools', 'mod_lti'), array('target' => '_blank'));
        $description = get_string('addlti_settingpreconfiguredtool_notools', 'block_opencast', $link);
        $additionalsettings->add(
            new admin_setting_configempty('block_opencast/addltipreconfiguredtool',
                get_string('addlti_settingpreconfiguredtool', 'block_opencast'),
                $description));
    }
    if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
        $additionalsettings->hide_if('block_opencast/addltipreconfiguredtool',
                'block_opencast/addltienabled', 'notchecked');
    }
}
$settings = null;