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

    // Empty $settings to prevent a single settings page from being created by lib/classes/plugininfo/block.php
    // because we will create several settings pages now.
    $settings = null;

    // Create admin settings category.
    $settingscategory = new admin_category('block_opencast', new lang_string('settings', 'block_opencast'));
    $ADMIN->add('blocksettings', $settingscategory);

    // Create empty settings page structure to make the site administration work on non-admin pages.
    if (!$ADMIN->fulltree) {
        // Setting page: General.
        $ADMIN->add('block_opencast', new admin_externalpage('block_opencast_generalsettings',
            get_string('general_settings', 'block_opencast'),
            new moodle_url('/blocks/opencast/adminsettings.php')));

        // Settings page: Appearance settings.
        $settingspage = new admin_settingpage('block_opencast_appearancesettings',
            get_string('appearance_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $settingspage);

        // Settings page: Additional settings.
        $settingspage = new admin_settingpage('block_opencast_additionalsettings',
            get_string('additional_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $settingspage);

        // Settings page: LTI module features.
        $settingspage = new admin_settingpage('block_opencast_ltimodulesettings',
            get_string('ltimodule_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $settingspage);

        // Settings page: Import videos features.
        $settingspage = new admin_settingpage('block_opencast_importvideossettings',
            get_string('importvideos_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $settingspage);

        // Create full settings page structure only if really needed.
    } else if ($ADMIN->fulltree) {
        // Setting page: General.
        $ADMIN->add('block_opencast', new admin_externalpage('block_opencast_generalsettings',
            get_string('general_settings', 'block_opencast'),
            new moodle_url('/blocks/opencast/adminsettings.php')));

        // Settings page: Appearance settings.
        $appearancesettings = new admin_settingpage('block_opencast_appearancesettings',
            get_string('appearance_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $appearancesettings);

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

        // Settings page: Additional settings.
        $additionalsettings = new admin_settingpage('block_opencast_additionalsettings',
            get_string('additional_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $additionalsettings);

        $installedplugins = core_plugin_manager::instance()->get_installed_plugins('local');
        $chunkuploadisinstalled = array_key_exists('chunkupload', $installedplugins);
        if ($chunkuploadisinstalled) {

            $additionalsettings->add(
                new admin_setting_heading('block_opencast/upload',
                    get_string('uploadsettings', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/enablechunkupload',
                    get_string('enablechunkupload', 'block_opencast'),
                    get_string('enablechunkupload_desc', 'block_opencast'), true));

            $sizelist = array(-1, 53687091200, 21474836480, 10737418240, 5368709120, 2147483648, 1610612736, 1073741824,
                536870912, 268435456, 134217728, 67108864);
            $filesizes = array();
            foreach ($sizelist as $sizebytes) {
                $filesizes[(string)intval($sizebytes)] = display_size($sizebytes);
            }

            $additionalsettings->add(new admin_setting_configselect('block_opencast/uploadfilelimit',
                get_string('uploadfilelimit', 'block_opencast'),
                get_string('uploadfilelimitdesc', 'block_opencast'),
                2147483648, $filesizes));
        }

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

        // Settings page: LTI module features.
        $ltimodulesettings = new admin_settingpage('block_opencast_ltimodulesettings',
            get_string('ltimodule_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $ltimodulesettings);

        // Add LTI series modules section.
        $ltimodulesettings->add(
            new admin_setting_heading('block_opencast/addlti_settingheader',
                get_string('addlti_settingheader', 'block_opencast'),
                ''));

        // Add LTI series modules: Enable feature.
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltienabled',
                get_string('addlti_settingenabled', 'block_opencast'),
                get_string('addlti_settingenabled_desc', 'block_opencast'), 0));

        // Add LTI series modules: Default LTI series module title.
        $ltimodulesettings->add(
            new admin_setting_configtext('block_opencast/addltidefaulttitle',
                get_string('addlti_settingdefaulttitle', 'block_opencast'),
                get_string('addlti_settingdefaulttitle_desc', 'block_opencast'),
                get_string('addlti_defaulttitle', 'block_opencast'),
                PARAM_TEXT));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltidefaulttitle',
                'block_opencast/addltienabled', 'notchecked');
        }

        // Add LTI series modules: Preconfigured LTI tool.
        $tools = \block_opencast\local\ltimodulemanager::get_preconfigured_tools();
        // If there are any tools to be selected.
        if (count($tools) > 0) {
            $ltimodulesettings->add(
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
            $ltimodulesettings->add(
                new admin_setting_configempty('block_opencast/addltipreconfiguredtool',
                    get_string('addlti_settingpreconfiguredtool', 'block_opencast'),
                    $description));
        }
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltipreconfiguredtool',
                'block_opencast/addltienabled', 'notchecked');
        }

        // Add LTI series modules: Intro.
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltiintro',
                get_string('addlti_settingintro', 'block_opencast'),
                get_string('addlti_settingintro_desc', 'block_opencast'), 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltiintro',
                'block_opencast/addltienabled', 'notchecked');
        }

        // Add LTI series modules: Section.
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltisection',
                get_string('addlti_settingsection', 'block_opencast'),
                get_string('addlti_settingsection_desc', 'block_opencast'), 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltisection',
                'block_opencast/addltienabled', 'notchecked');
        }

        // Add LTI series modules: Availability.
        $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
        $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
        $description = get_string('addlti_settingavailability_desc', 'block_opencast') . '<br />' .
            get_string('addlti_settingavailability_note', 'block_opencast', $link);
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltiavailability',
                get_string('addlti_settingavailability', 'block_opencast'),
                $description, 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltiavailability',
                'block_opencast/addltienabled', 'notchecked');
        }

        // Add LTI episode modules section.
        $ltimodulesettings->add(
            new admin_setting_heading('block_opencast/addltiepisode_settingheader',
                get_string('addltiepisode_settingheader', 'block_opencast'),
                ''));

        // Add LTI episode modules: Enable feature.
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltiepisodeenabled',
                get_string('addltiepisode_settingenabled', 'block_opencast'),
                get_string('addltiepisode_settingenabled_desc', 'block_opencast'), 0));

        // Add LTI episode modules: Preconfigured LTI tool.
        $tools = \block_opencast\local\ltimodulemanager::get_preconfigured_tools();
        // If there are any tools to be selected.
        if (count($tools) > 0) {
            $ltimodulesettings->add(
                new admin_setting_configselect('block_opencast/addltiepisodepreconfiguredtool',
                    get_string('addltiepisode_settingpreconfiguredtool', 'block_opencast'),
                    get_string('addltiepisode_settingpreconfiguredtool_desc', 'block_opencast'),
                    null,
                    $tools));

            // If there aren't any preconfigured tools to be selected.
        } else {
            // Add an empty element to at least create the setting when the plugin is installed.
            // Additionally, show some information text where to add preconfigured tools.
            $url = '/admin/settings.php?section=modsettinglti';
            $link = html_writer::link($url, get_string('manage_tools', 'mod_lti'), array('target' => '_blank'));
            $description = get_string('addltiepisode_settingpreconfiguredtool_notools', 'block_opencast', $link);
            $ltimodulesettings->add(
                new admin_setting_configempty('block_opencast/addltiepisodepreconfiguredtool',
                    get_string('addltiepisode_settingpreconfiguredtool', 'block_opencast'),
                    $description));
        }
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltiepisodepreconfiguredtool',
                'block_opencast/addltiepisodeenabled', 'notchecked');
        }

        // Add LTI episode modules: Intro.
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltiepisodeintro',
                get_string('addltiepisode_settingintro', 'block_opencast'),
                get_string('addltiepisode_settingintro_desc', 'block_opencast'), 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltiepisodeintro',
                'block_opencast/addltiepisodeenabled', 'notchecked');
        }

        // Add LTI episode modules: Section.
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltiepisodesection',
                get_string('addltiepisode_settingsection', 'block_opencast'),
                get_string('addltiepisode_settingsection_desc', 'block_opencast'), 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltiepisodesection',
                'block_opencast/addltiepisodeenabled', 'notchecked');
        }

        // Add LTI episode modules: Availability.
        $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
        $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
        $description = get_string('addltiepisode_settingavailability_desc', 'block_opencast') . '<br />' .
            get_string('addlti_settingavailability_note', 'block_opencast', $link);
        $ltimodulesettings->add(
            new admin_setting_configcheckbox('block_opencast/addltiepisodeavailability',
                get_string('addltiepisode_settingavailability', 'block_opencast'),
                $description, 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $ltimodulesettings->hide_if('block_opencast/addltiepisodeavailability',
                'block_opencast/addltiepisodeenabled', 'notchecked');
        }

        // Settings page: Import videos features.
        $importvideossettings = new admin_settingpage('block_opencast_importvideossettings',
            get_string('importvideos_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $importvideossettings);

        // Import videos section.
        $importvideossettings->add(
            new admin_setting_heading('block_opencast/importvideos_settingheader',
                get_string('importvideos_settingheader', 'block_opencast'),
                ''));

        // Import videos: Enable feature.
        $importvideossettings->add(
            new admin_setting_configcheckbox('block_opencast/importvideosenabled',
                get_string('importvideos_settingenabled', 'block_opencast'),
                get_string('importvideos_settingenabled_desc', 'block_opencast'), 1));

        // Import videos: Duplicate workflow.
        $importvideossettings->add(
            new \block_opencast\admin_setting_configselect_opencastworkflow('block_opencast/duplicateworkflow',
                get_string('duplicateworkflow', 'block_opencast'),
                get_string('duplicateworkflowdesc', 'block_opencast'),
                'api'));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $importvideossettings->hide_if('block_opencast/duplicateworkflow',
                'block_opencast/importvideosenabled', 'notchecked');
        }

        // Import videos: Enable import videos within Moodle core course import wizard feature.
        $importvideossettings->add(
            new admin_setting_configcheckbox('block_opencast/importvideoscoreenabled',
                get_string('importvideos_settingcoreenabled', 'block_opencast'),
                get_string('importvideos_settingcoreenabled_desc', 'block_opencast'), 1));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $importvideossettings->hide_if('block_opencast/importvideoscoreenabled',
                'block_opencast/importvideosenabled', 'notchecked');
            $importvideossettings->hide_if('block_opencast/importvideoscoreenabled',
                'block_opencast/duplicateworkflow', 'eq', '');
        }

        // Import videos: Enable manual import videos feature.
        $importvideossettings->add(
            new admin_setting_configcheckbox('block_opencast/importvideosmanualenabled',
                get_string('importvideos_settingmanualenabled', 'block_opencast'),
                get_string('importvideos_settingmanualenabled_desc', 'block_opencast'), 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $importvideossettings->hide_if('block_opencast/importvideosmanualenabled',
                'block_opencast/importvideosenabled', 'notchecked');
            $importvideossettings->hide_if('block_opencast/importvideosmanualenabled',
                'block_opencast/duplicateworkflow', 'eq', '');
        }

        // Import videos: Handle Opencast series modules during manual import.
        $importvideossettings->add(
            new admin_setting_configcheckbox('block_opencast/importvideoshandleseriesenabled',
                get_string('importvideos_settinghandleseriesenabled', 'block_opencast'),
                get_string('importvideos_settinghandleseriesenabled_desc', 'block_opencast'), 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $importvideossettings->hide_if('block_opencast/importvideoshandleseriesenabled',
                'block_opencast/importvideosenabled', 'notchecked');
            $importvideossettings->hide_if('block_opencast/importvideoshandleseriesenabled',
                'block_opencast/duplicateworkflow', 'eq', '');
            $importvideossettings->hide_if('block_opencast/importvideoshandleseriesenabled',
                'block_opencast/importvideosmanualenabled', 'notchecked');
        }

        // Import videos: Handle Opencast episode modules during manual import.
        $importvideossettings->add(
            new admin_setting_configcheckbox('block_opencast/importvideoshandleepisodeenabled',
                get_string('importvideos_settinghandleepisodeenabled', 'block_opencast'),
                get_string('importvideos_settinghandleepisodeenabled_desc', 'block_opencast'), 0));
        if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
            $importvideossettings->hide_if('block_opencast/importvideoshandleepisodeenabled',
                'block_opencast/importvideosenabled', 'notchecked');
            $importvideossettings->hide_if('block_opencast/importvideoshandleepisodeenabled',
                'block_opencast/duplicateworkflow', 'eq', '');
            $importvideossettings->hide_if('block_opencast/importvideoshandleepisodeenabled',
                'block_opencast/importvideosmanualenabled', 'notchecked');
        }

        if (core_plugin_manager::instance()->get_plugin_info('mod_opencast')) {

            // Add Opencast Activity modules section.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/addactivity_settingheader',
                    get_string('addactivity_settingheader', 'block_opencast'),
                    ''));

            // Add Opencast Activity series modules: Enable feature.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivityenabled',
                    get_string('addactivity_settingenabled', 'block_opencast'),
                    get_string('addactivity_settingenabled_desc', 'block_opencast'), 0));

            // Add Opencast Activity series modules: Default Opencast Activity series module title.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/addactivitydefaulttitle',
                    get_string('addactivity_settingdefaulttitle', 'block_opencast'),
                    get_string('addactivity_settingdefaulttitle_desc', 'block_opencast'),
                    get_string('addactivity_defaulttitle', 'block_opencast'),
                    PARAM_TEXT));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/addactivitydefaulttitle',
                    'block_opencast/addactivityenabled', 'notchecked');
            }

            // Add Opencast Activity series modules: Intro.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivityintro',
                    get_string('addactivity_settingintro', 'block_opencast'),
                    get_string('addactivity_settingintro_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/addactivityintro',
                    'block_opencast/addactivityenabled', 'notchecked');
            }

            // Add Opencast Activity series modules: Section.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivitysection',
                    get_string('addactivity_settingsection', 'block_opencast'),
                    get_string('addactivity_settingsection_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/addactivitysection',
                    'block_opencast/addactivityenabled', 'notchecked');
            }

            // Add Opencast Activity series modules: Availability.
            $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
            $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
            $description = get_string('addactivity_settingavailability_desc', 'block_opencast') . '<br />' .
                get_string('addactivity_settingavailability_note', 'block_opencast', $link);
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivityavailability',
                    get_string('addactivity_settingavailability', 'block_opencast'),
                    $description, 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/addactivityavailability',
                    'block_opencast/addactivityenabled', 'notchecked');
            }

            // Add Opencast Activity episode modules: Enable feature.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivityepisodeenabled',
                    get_string('addactivityepisode_settingenabled', 'block_opencast'),
                    get_string('addactivityepisode_settingenabled_desc', 'block_opencast'), 0));

            // Add Opencast Activity episode modules: Intro.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivityepisodeintro',
                    get_string('addactivityepisode_settingintro', 'block_opencast'),
                    get_string('addactivityepisode_settingintro_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/addactivityepisodeintro',
                    'block_opencast/addactivityepisodeenabled', 'notchecked');
            }

            // Add Opencast Activity episode modules: Section.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivityepisodesection',
                    get_string('addactivityepisode_settingsection', 'block_opencast'),
                    get_string('addactivityepisode_settingsection_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/addactivityepisodesection',
                    'block_opencast/addactivityepisodeenabled', 'notchecked');
            }

            // Add Opencast Activity episode modules: Availability.
            $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
            $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
            $description = get_string('addactivityepisode_settingavailability_desc', 'block_opencast') . '<br />' .
                get_string('addactivity_settingavailability_note', 'block_opencast', $link);
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/addactivityepisodeavailability',
                    get_string('addactivityepisode_settingavailability', 'block_opencast'),
                    $description, 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/addactivityepisodeavailability',
                    'block_opencast/addactivityepisodeenabled', 'notchecked');
            }
        }
    }
}
$settings = null;
