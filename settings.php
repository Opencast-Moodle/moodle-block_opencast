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
use block_opencast\opencast_connection_exception;
use block_opencast\setting_helper;
use block_opencast\setting_default_manager;
use core\notification;
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
        $sharedsettings = new admin_settingpage('block_opencast_sharedsettings', get_string('shared_settings', 'block_opencast'));
        $ADMIN->add('block_opencast', $sharedsettings);

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

            // Settings page: Appearance settings.
            $settingspage = new admin_settingpage('block_opencast_appearancesettings_' . $instance->id,
                get_string('appearance_settings', 'block_opencast'));
            $ADMIN->add($category, $settingspage);

            // Settings page: Additional settings.
            $settingspage = new admin_settingpage('block_opencast_additionalsettings_' . $instance->id,
                get_string('additional_settings', 'block_opencast'));
            $ADMIN->add($category, $settingspage);

            // Settings page: LTI module features.
            $settingspage = new admin_settingpage('block_opencast_ltimodulesettings_' . $instance->id,
                get_string('ltimodule_settings', 'block_opencast'));
            $ADMIN->add($category, $settingspage);

            // Settings page: Import videos features.
            $settingspage = new admin_settingpage('block_opencast_importvideossettings_' . $instance->id,
                get_string('importvideos_settings', 'block_opencast'));
            $ADMIN->add($category, $settingspage);
        }

        // Because we are using the calls to get workflows actively in the setting, therefore we need to narrow it down only
        // when needed. So we check if this setting page is currently requested.
    } else if ($ADMIN->fulltree &&
        (strpos($PAGE->pagetype, 'block_opencast') !== false || // When only landing on the admin settings page for block_opencast.
            ($PAGE->pagetype == 'admin-upgradesettings' && $PAGE->pagelayout == 'maintenance') || // During upgrade or install.
            (environment_util::is_cli_application() && !environment_util::is_moodle_plugin_ci_workflow()))) {
        if ($PAGE->state !== moodle_page::STATE_IN_BODY) {
            $PAGE->requires->css('/blocks/opencast/css/tabulator.min.css');
            $PAGE->requires->css('/blocks/opencast/css/tabulator_bootstrap4.min.css');
        }

        $sharedsettings = new admin_settingpage('block_opencast_sharedsettings', get_string('shared_settings', 'block_opencast'));
        $sharedsettings->add(
            new admin_setting_configtext('block_opencast/cachevalidtime',
                get_string('cachevalidtime', 'block_opencast'),
                get_string('cachevalidtime_desc', 'block_opencast'), 500, PARAM_INT));

        // Upload timeout.
        $sharedsettings->add(
            new admin_setting_configtext('block_opencast/uploadtimeout',
                get_string('uploadtimeout', 'block_opencast'),
                get_string('uploadtimeoutdesc', 'block_opencast'), 60, PARAM_INT));

        // Failed upload retry limit.
        $sharedsettings->add(
            new admin_setting_configtext('block_opencast/faileduploadretrylimit',
                get_string('faileduploadretrylimit', 'block_opencast'),
                get_string('faileduploadretrylimitdesc', 'block_opencast'), 0, PARAM_INT));

        $ADMIN->add('block_opencast', $sharedsettings);

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
            $opencasterror = false;

            // Initialize the default settings for each instance.
            setting_default_manager::init_regirstered_defaults($instance->id);

            // Setup js.
            $rolesdefault = setting_default_manager::get_default_roles();
            $metadatadefault = setting_default_manager::get_default_metadata();
            $metadataseriesdefault = setting_default_manager::get_default_metadataseries();
            $defaulttranscriptionflavors = setting_default_manager::get_default_transcriptionflavors();

            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpname_' . $instance->id,
                'helpbtnname_' . $instance->id, 'descriptionmdfn', 'block_opencast'));
            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpparams_' . $instance->id,
                'helpbtnparams_' . $instance->id, 'catalogparam', 'block_opencast'));
            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpdescription_' . $instance->id,
                'helpbtndescription_' . $instance->id, 'descriptionmdfd', 'block_opencast'));
            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpdefaultable_' . $instance->id,
                'helpbtndefaultable_' . $instance->id, 'descriptionmddefaultable', 'block_opencast'));
            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpbatchable_' . $instance->id,
                'helpbtnbatchable_' . $instance->id, 'descriptionmdbatchable', 'block_opencast'));
            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpreadonly_' . $instance->id,
                'helpbtnreadonly_' . $instance->id, 'descriptionmdreadonly', 'block_opencast'));

            $rolessetting = new admin_setting_configtext('block_opencast/roles_' . $instance->id,
                get_string('aclrolesname', 'block_opencast'),
                get_string('aclrolesnamedesc',
                    'block_opencast'), $rolesdefault);

            $dcmitermsnotice = get_string('dcmitermsnotice', 'block_opencast');
            $metadatasetting = new admin_setting_configtext('block_opencast/metadata_' . $instance->id,
                get_string('metadata', 'block_opencast'),
                get_string('metadatadesc', 'block_opencast') . $dcmitermsnotice, $metadatadefault);

            $metadataseriessetting = new admin_setting_configtext('block_opencast/metadataseries_' . $instance->id,
                get_string('metadataseries', 'block_opencast'),
                get_string('metadataseriesdesc', 'block_opencast') . $dcmitermsnotice, $metadataseriesdefault);

            $transcriptionflavors = new admin_setting_configtext('block_opencast/transcriptionflavors_' . $instance->id,
                get_string('transcriptionflavors', 'block_opencast'),
                get_string('transcriptionflavors_desc', 'block_opencast'), $defaulttranscriptionflavors);

            // Crashes if plugins.php is opened because css cannot be included anymore.
            if ($PAGE->state !== moodle_page::STATE_IN_BODY) {
                $PAGE->requires->js_call_amd('block_opencast/block_settings', 'init', [
                    $rolessetting->get_id(),
                    $metadatasetting->get_id(),
                    $metadataseriessetting->get_id(),
                    $transcriptionflavors->get_id(),
                    $instance->id,
                ]);
            }

            $generalsettings->add(
                new admin_setting_heading('block_opencast/general_settings_' . $instance->id,
                    get_string('cronsettings', 'block_opencast'),
                    ''));

            $url = new moodle_url('/admin/tool/task/scheduledtasks.php');
            $link = html_writer::link($url, get_string('pluginname', 'tool_task'), ['target' => '_blank']);
            $generalsettings->add(
                new admin_setting_configtext('block_opencast/limituploadjobs_' . $instance->id,
                    get_string('limituploadjobs', 'block_opencast'),
                    get_string('limituploadjobsdesc', 'block_opencast', $link), 1, PARAM_INT));

            $workflowchoices = setting_helper::load_workflow_choices($instance->id, 'upload');
            if ($workflowchoices instanceof opencast_connection_exception ||
                $workflowchoices instanceof empty_configuration_exception) {
                $opencasterror = $workflowchoices->getMessage();
                $workflowchoices = [null => get_string('adminchoice_noconnection', 'block_opencast')];
            }

            $generalsettings->add(new admin_setting_configselect('block_opencast/uploadworkflow_' . $instance->id,
                get_string('uploadworkflow', 'block_opencast'),
                get_string('uploadworkflowdesc', 'block_opencast'),
                'ng-schedule-and-upload', $workflowchoices
            ));

            $generalsettings->add(new admin_setting_configcheckbox('block_opencast/enableuploadwfconfigpanel_' . $instance->id,
                get_string('enableuploadwfconfigpanel', 'block_opencast'),
                get_string('enableuploadwfconfigpaneldesc', 'block_opencast'),
                0
            ));

            $generalsettings->add(new admin_setting_configtext('block_opencast/alloweduploadwfconfigs_' . $instance->id,
                get_string('alloweduploadwfconfigs', 'block_opencast'),
                get_string('alloweduploadwfconfigsdesc', 'block_opencast'),
                '',
                PARAM_TEXT
            ));

            $generalsettings->hide_if('block_opencast/alloweduploadwfconfigs_' . $instance->id,
                'block_opencast/enableuploadwfconfigpanel_' . $instance->id, 'notchecked');

            $generalsettings->add(new admin_setting_configcheckbox('block_opencast/publishtoengage_' . $instance->id,
                get_string('publishtoengage', 'block_opencast'),
                get_string('publishtoengagedesc', 'block_opencast'),
                0
            ));

            $generalsettings->add(new admin_setting_configcheckbox('block_opencast/ingestupload_' . $instance->id,
                get_string('ingestupload', 'block_opencast'),
                get_string('ingestuploaddesc', 'block_opencast'),
                0
            ));

            $generalsettings->add(new admin_setting_configcheckbox('block_opencast/reuseexistingupload_' . $instance->id,
                get_string('reuseexistingupload', 'block_opencast'),
                get_string('reuseexistinguploaddesc', 'block_opencast'),
                0
            ));

            $generalsettings->hide_if('block_opencast/reuseexistingupload_' . $instance->id,
                'block_opencast/ingestupload_' . $instance->id, 'checked');

            $generalsettings->add(new admin_setting_configcheckbox('block_opencast/allowunassign_' . $instance->id,
                get_string('allowunassign', 'block_opencast'),
                get_string('allowunassigndesc', 'block_opencast'),
                0
            ));


            $workflowchoices = setting_helper::load_workflow_choices($instance->id, 'delete');
            if ($workflowchoices instanceof opencast_connection_exception ||
                $workflowchoices instanceof empty_configuration_exception) {
                $opencasterror = $workflowchoices->getMessage();
                $workflowchoices = [null => get_string('adminchoice_noconnection', 'block_opencast')];
            }

            $generalsettings->add(new admin_setting_configselect('block_opencast/deleteworkflow_' . $instance->id,
                    get_string('deleteworkflow', 'block_opencast'),
                    get_string('deleteworkflowdesc', 'block_opencast'),
                    null, $workflowchoices)
            );

            $generalsettings->add(new admin_setting_configcheckbox('block_opencast/adhocfiledeletion_' . $instance->id,
                get_string('adhocfiledeletion', 'block_opencast'),
                get_string('adhocfiledeletiondesc', 'block_opencast'),
                0
            ));

            $generalsettings->add(new admin_setting_filetypes('block_opencast/uploadfileextensions_' . $instance->id,
                new lang_string('uploadfileextensions', 'block_opencast'),
                get_string('uploadfileextensionsdesc', 'block_opencast', $CFG->wwwroot . '/admin/tool/filetypes/index.php')
            ));

            $generalsettings->add(new admin_setting_configtext('block_opencast/maxseries_' . $instance->id,
                new lang_string('maxseries', 'block_opencast'),
                get_string('maxseriesdesc', 'block_opencast'), 3, PARAM_INT
            ));

            // Batch upload setting.
            $uploadtimeouturl = new moodle_url('/admin/settings.php?section=block_opencast_sharedsettings');
            $uploadtimeoutlink = html_writer::link($uploadtimeouturl,
                get_string('uploadtimeout', 'block_opencast'), ['target' => '_blank']);

            $octoolshorturl = '/admin/settings.php?section=tool_opencast_configuration';
            if ($multiocinstance) {
                $octoolshorturl .= '_' . $instance->id;
            }
            $toolopencastinstanceurl = new moodle_url($octoolshorturl);
            $toolopencastinstancelink = html_writer::link($toolopencastinstanceurl,
                get_string('configuration_instance', 'tool_opencast', $instance->name), ['target' => '_blank']);
            $stringobj = new \stdClass();
            $stringobj->uploadtimeoutlink = $uploadtimeoutlink;
            $stringobj->toolopencastinstancelink = $toolopencastinstancelink;
            $generalsettings->add(new admin_setting_configcheckbox('block_opencast/batchuploadenabled_' . $instance->id,
                get_string('batchupload_setting', 'block_opencast'),
                get_string('batchupload_setting_desc', 'block_opencast', $stringobj),
                1
            ));

            $generalsettings->add(
                new admin_setting_heading('block_opencast/block_header_' . $instance->id,
                    get_string('blocksettings', 'block_opencast'),
                    ''));

            $generalsettings->add(
                new admin_setting_configtext('block_opencast/limitvideos_' . $instance->id,
                    get_string('limitvideos', 'block_opencast'),
                    get_string('limitvideosdesc', 'block_opencast'), 5, PARAM_INT));

            $generalsettings->add(
                new admin_setting_heading('block_opencast/groupseries_header_' . $instance->id,
                    get_string('groupseries_header', 'block_opencast'),
                    ''));

            $generalsettings->add(
                new admin_setting_configcheckbox('block_opencast/group_creation_' . $instance->id,
                    get_string('groupcreation', 'block_opencast'),
                    get_string('groupcreationdesc', 'block_opencast'), 0
                ));

            $generalsettings->add(
                new admin_setting_configtext('block_opencast/group_name_' . $instance->id,
                    get_string('groupname', 'block_opencast'),
                    get_string('groupnamedesc', 'block_opencast'), 'Moodle_course_[COURSEID]', PARAM_TEXT));

            $generalsettings->add(
                new admin_setting_configtext('block_opencast/series_name_' . $instance->id,
                    get_string('seriesname', 'block_opencast'),
                    get_string('seriesnamedesc', 'block_opencast', $link), 'Course_Series_[COURSEID]', PARAM_TEXT));

            $generalsettings->add(
                new admin_setting_heading('block_opencast/roles_header_' . $instance->id,
                    get_string('aclrolesname', 'block_opencast'),
                    ''));


            $workflowchoices = setting_helper::load_workflow_choices($instance->id, 'archive');
            if ($workflowchoices instanceof opencast_connection_exception ||
                $workflowchoices instanceof empty_configuration_exception) {
                $opencasterror = $workflowchoices->getMessage();
                $workflowchoices = [null => get_string('adminchoice_noconnection', 'block_opencast')];
            }
            $generalsettings->add(new admin_setting_configselect('block_opencast/workflow_roles_' . $instance->id,
                    get_string('workflowrolesname', 'block_opencast'),
                    get_string('workflowrolesdesc', 'block_opencast'),
                    null, $workflowchoices)
            );

            $generalsettings->add($rolessetting);
            $generalsettings->add(new admin_setting_configeditabletable('block_opencast/rolestable_' .
                $instance->id, 'rolestable_' . $instance->id,
                get_string('addrole', 'block_opencast')));

            $roleownersetting = new admin_setting_configtextvalidate('block_opencast/aclownerrole_' . $instance->id,
                get_string('aclownerrole', 'block_opencast'),
                get_string('aclownerrole_desc', 'block_opencast'), '');
            $roleownersetting->set_validate_function([setting_helper::class, 'validate_aclownerrole_setting']);
            $generalsettings->add($roleownersetting);

            $generalsettings->add(
                new admin_setting_heading('block_opencast/metadata_header_' . $instance->id,
                    get_string('metadata', 'block_opencast'),
                    ''));

            $generalsettings->add($metadatasetting);
            $generalsettings->add(new admin_setting_configeditabletable('block_opencast/metadatatable_' .
                $instance->id, 'metadatatable_' . $instance->id,
                get_string('addcatalog', 'block_opencast')));

            $generalsettings->add(
                new admin_setting_heading('block_opencast/metadataseries_header_' . $instance->id,
                    get_string('metadataseries', 'block_opencast'),
                    ''));

            $generalsettings->add($metadataseriessetting);
            $generalsettings->add(new admin_setting_configeditabletable('block_opencast/metadataseriestable_' .
                $instance->id, 'metadataseriestable_' . $instance->id,
                get_string('addcatalog', 'block_opencast')));

            // Settings page: Appearance settings.
            $appearancesettings = new admin_settingpage('block_opencast_appearancesettings_' . $instance->id,
                get_string('appearance_settings', 'block_opencast'));
            $ADMIN->add($category, $appearancesettings);

            $appearancesettings->add(
                new admin_setting_heading('block_opencast/appearance_overview_' . $instance->id,
                    get_string('appearance_overview_settingheader', 'block_opencast'),
                    ''));

            $appearancesettings->add(
                new admin_setting_configcheckbox('block_opencast/showpublicationchannels_' . $instance->id,
                    get_string('appearance_overview_settingshowpublicationchannels', 'block_opencast'),
                    get_string('appearance_overview_settingshowpublicationchannels_desc', 'block_opencast'), 1));

            $appearancesettings->add(
                new admin_setting_configcheckbox('block_opencast/showenddate_' . $instance->id,
                    get_string('appearance_overview_settingshowenddate', 'block_opencast'),
                    get_string('appearance_overview_settingshowenddate_desc', 'block_opencast'), 1));

            $appearancesettings->add(
                new admin_setting_configcheckbox('block_opencast/showlocation_' . $instance->id,
                    get_string('appearance_overview_settingshowlocation', 'block_opencast'),
                    get_string('appearance_overview_settingshowlocation_desc', 'block_opencast'), 1));

            // Settings page: Additional settings.
            $additionalsettings = new admin_settingpage('block_opencast_additionalsettings_' . $instance->id,
                get_string('additional_settings', 'block_opencast'));
            $ADMIN->add($category, $additionalsettings);

            $installedplugins = core_plugin_manager::instance()->get_installed_plugins('local');
            $chunkuploadisinstalled = array_key_exists('chunkupload', $installedplugins);
            if ($chunkuploadisinstalled) {

                $additionalsettings->add(
                    new admin_setting_heading('block_opencast/upload_' . $instance->id,
                        get_string('uploadsettings', 'block_opencast'),
                        ''));

                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/enablechunkupload_' . $instance->id,
                        get_string('enablechunkupload', 'block_opencast'),
                        get_string('enablechunkupload_desc', 'block_opencast'), true));

                $sizelist = [-1, 53687091200, 21474836480, 10737418240, 5368709120, 2147483648, 1610612736, 1073741824,
                    536870912, 268435456, 134217728, 67108864, ];
                $filesizes = [];
                foreach ($sizelist as $sizebytes) {
                    $filesizes[(string)intval($sizebytes)] = display_size($sizebytes);
                }

                $additionalsettings->add(new admin_setting_configselect('block_opencast/uploadfilelimit_' . $instance->id,
                    get_string('uploadfilelimit', 'block_opencast'),
                    get_string('uploadfilelimitdesc', 'block_opencast'),
                    2147483648, $filesizes));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/uploadfilelimit_' . $instance->id,
                        'block_opencast/enablechunkupload_' . $instance->id, 'notchecked');
                }

                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/offerchunkuploadalternative_' . $instance->id,
                        get_string('offerchunkuploadalternative', 'block_opencast'),
                        get_string('offerchunkuploadalternative_desc', 'block_opencast',
                            get_string('usedefaultfilepicker', 'block_opencast')), true));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/offerchunkuploadalternative_' . $instance->id,
                        'block_opencast/enablechunkupload_' . $instance->id, 'notchecked');
                }
            }

            $additionalsettings->add(
                new admin_setting_heading('block_opencast/opencast_studio_' . $instance->id,
                    get_string('opencaststudiointegration', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/enable_opencast_studio_link_' . $instance->id,
                    get_string('enableopencaststudiolink', 'block_opencast'),
                    get_string('enableopencaststudiolink_desc', 'block_opencast'), 0));

            // New tab config for Studio.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/open_studio_in_new_tab_' . $instance->id,
                    get_string('opencaststudionewtab', 'block_opencast'),
                    get_string('opencaststudionewtab_desc', 'block_opencast'), 1));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/opencast_studio_baseurl_' . $instance->id,
                    get_string('opencaststudiobaseurl', 'block_opencast'),
                    get_string('opencaststudiobaseurl_desc', 'block_opencast'), ''));

            // Studio redirect button settings.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/show_opencast_studio_return_btn_' . $instance->id,
                    get_string('enableopencaststudioreturnbtn', 'block_opencast'),
                    get_string('enableopencaststudioreturnbtn_desc', 'block_opencast'), 0));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/opencast_studio_return_btn_label_' . $instance->id,
                    get_string('opencaststudioreturnbtnlabel', 'block_opencast'),
                    get_string('opencaststudioreturnbtnlabel_desc', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/opencast_studio_return_url_' . $instance->id,
                    get_string('opencaststudioreturnurl', 'block_opencast'),
                    get_string('opencaststudioreturnurl_desc', 'block_opencast'),
                    '/blocks/opencast/index.php?courseid=[COURSEID]&ocinstanceid=[OCINSTANCEID]'));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/opencast_studio_custom_settings_filename_' . $instance->id,
                    get_string('opencaststudiocustomsettingsfilename', 'block_opencast'),
                    get_string('opencaststudiocustomsettingsfilename_desc', 'block_opencast'),
                    ''));

            // Opencast Editor Integration in additional feature settings.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/opencast_videoeditor_' . $instance->id,
                    get_string('opencasteditorintegration', 'block_opencast'),
                    ''));

            // The Generall Integration Permission.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/enable_opencast_editor_link_' . $instance->id,
                    get_string('enableopencasteditorlink', 'block_opencast'),
                    get_string('enableopencasteditorlink_desc', 'block_opencast'), 0));

            // The External base url to call editor (if any). The opencast instance URL will be used if empty.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/editorbaseurl_' . $instance->id,
                    get_string('editorbaseurl', 'block_opencast'),
                    get_string('editorbaseurl_desc', 'block_opencast'), ""));

            // The Editor endpoint url. It defines where to look for the editor in base url.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/editorendpointurl_' . $instance->id,
                    get_string('editorendpointurl', 'block_opencast'),
                    get_string('editorendpointurl_desc', 'block_opencast'), "/editor-ui/index.html?mediaPackageId="));

            // Opencast Video Player in additional feature settings.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/opencast_access_video_' . $instance->id,
                    get_string('engageplayerintegration', 'block_opencast'),
                    ''));

            // The link to the engage player.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/engageurl_' . $instance->id,
                    get_string('engageurl', 'block_opencast'),
                    get_string('engageurl_desc', 'block_opencast'), ""));

            // Notifications in additional features settings.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/notifications_' . $instance->id,
                    get_string('notifications_settings_header', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/eventstatusnotificationenabled_' . $instance->id,
                    get_string('notificationeventstatus', 'block_opencast'),
                    get_string('notificationeventstatus_desc', 'block_opencast'), 0));

            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/eventstatusnotifyteachers_' . $instance->id,
                    get_string('notificationeventstatusteachers', 'block_opencast'),
                    get_string('notificationeventstatusteachers_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/eventstatusnotifyteachers_' . $instance->id,
                    'block_opencast/eventstatusnotificationenabled_' . $instance->id, 'notchecked');
            }

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/eventstatusnotificationdeletion_' . $instance->id,
                    get_string('notificationeventstatusdeletion', 'block_opencast'),
                    get_string('notificationeventstatusdeletion_desc', 'block_opencast'), 0, PARAM_INT));


            // Control ACL section.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/acl_settingheader_' . $instance->id,
                    get_string('acl_settingheader', 'block_opencast'),
                    ''));

            // Control ACL: Enable feature.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/aclcontrol_' . $instance->id,
                    get_string('acl_settingcontrol', 'block_opencast'),
                    get_string('acl_settingcontrol_desc', 'block_opencast'), 1));

            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/aclcontrolafter_' . $instance->id,
                    get_string('acl_settingcontrolafter', 'block_opencast'),
                    get_string('acl_settingcontrolafter_desc', 'block_opencast'), 1));

            // Control ACL: Enable group restriction.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/aclcontrolgroup_' . $instance->id,
                    get_string('acl_settingcontrolgroup', 'block_opencast'),
                    get_string('acl_settingcontrolgroup_desc', 'block_opencast'), 1));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/aclcontrolgroup_' . $instance->id,
                    'block_opencast/aclcontrolafter_' . $instance->id, 'notchecked');
            }

            // Control ACL: Waiting time for scheduled visibility change.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/aclcontrolwaitingtime_' . $instance->id,
                    get_string('acl_settingcontrolwaitingtime', 'block_opencast'),
                    get_string('acl_settingcontrolwaitingtime_desc', 'block_opencast'),
                    visibility_helper::DEFAULT_WAITING_TIME, PARAM_INT));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $additionalsettings->hide_if('block_opencast/aclcontrolwaitingtime_' . $instance->id,
                    'block_opencast/aclcontrolafter_' . $instance->id, 'notchecked');
            }

            if (core_plugin_manager::instance()->get_plugin_info('mod_opencast')) {

                // Add Opencast Activity modules section.
                $additionalsettings->add(
                    new admin_setting_heading('block_opencast/addactivity_settingheader_' . $instance->id,
                        get_string('addactivity_settingheader', 'block_opencast'),
                        ''));

                // Add Opencast Activity series modules: Enable feature.
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivityenabled_' . $instance->id,
                        get_string('addactivity_settingenabled', 'block_opencast'),
                        get_string('addactivity_settingenabled_desc', 'block_opencast'), 0));

                // Add Opencast Activity series modules: Default Opencast Activity series module title.
                $additionalsettings->add(
                    new admin_setting_configtext('block_opencast/addactivitydefaulttitle_' . $instance->id,
                        get_string('addactivity_settingdefaulttitle', 'block_opencast'),
                        get_string('addactivity_settingdefaulttitle_desc', 'block_opencast'),
                        get_string('addactivity_defaulttitle', 'block_opencast'),
                        PARAM_TEXT));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/addactivitydefaulttitle_' . $instance->id,
                        'block_opencast/addactivityenabled_' . $instance->id, 'notchecked');
                }

                // Add Opencast Activity series modules: Intro.
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivityintro_' . $instance->id,
                        get_string('addactivity_settingintro', 'block_opencast'),
                        get_string('addactivity_settingintro_desc', 'block_opencast'), 0));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/addactivityintro_' . $instance->id,
                        'block_opencast/addactivityenabled_' . $instance->id, 'notchecked');
                }

                // Add Opencast Activity series modules: Section.
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivitysection_' . $instance->id,
                        get_string('addactivity_settingsection', 'block_opencast'),
                        get_string('addactivity_settingsection_desc', 'block_opencast'), 0));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/addactivitysection_' . $instance->id,
                        'block_opencast/addactivityenabled_' . $instance->id, 'notchecked');
                }

                // Add Opencast Activity series modules: Availability.
                $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
                $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), ['target' => '_blank']);
                $description = get_string('addactivity_settingavailability_desc', 'block_opencast') . '<br />' .
                    get_string('addactivity_settingavailability_note', 'block_opencast', $link);
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivityavailability_' . $instance->id,
                        get_string('addactivity_settingavailability', 'block_opencast'),
                        $description, 0));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/addactivityavailability_' . $instance->id,
                        'block_opencast/addactivityenabled_' . $instance->id, 'notchecked');
                }

                // Add Opencast Activity episode modules: Enable feature.
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivityepisodeenabled_' . $instance->id,
                        get_string('addactivityepisode_settingenabled', 'block_opencast'),
                        get_string('addactivityepisode_settingenabled_desc', 'block_opencast'), 0));

                // Add Opencast Activity episode modules: Intro.
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivityepisodeintro_' . $instance->id,
                        get_string('addactivityepisode_settingintro', 'block_opencast'),
                        get_string('addactivityepisode_settingintro_desc', 'block_opencast'), 0));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/addactivityepisodeintro_' . $instance->id,
                        'block_opencast/addactivityepisodeenabled_' . $instance->id, 'notchecked');
                }

                // Add Opencast Activity episode modules: Section.
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivityepisodesection_' . $instance->id,
                        get_string('addactivityepisode_settingsection', 'block_opencast'),
                        get_string('addactivityepisode_settingsection_desc', 'block_opencast'), 0));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/addactivityepisodesection_' . $instance->id,
                        'block_opencast/addactivityepisodeenabled_' . $instance->id, 'notchecked');
                }

                // Add Opencast Activity episode modules: Availability.
                $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
                $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), ['target' => '_blank']);
                $description = get_string('addactivityepisode_settingavailability_desc', 'block_opencast') . '<br />' .
                    get_string('addactivity_settingavailability_note', 'block_opencast', $link);
                $additionalsettings->add(
                    new admin_setting_configcheckbox('block_opencast/addactivityepisodeavailability_' . $instance->id,
                        get_string('addactivityepisode_settingavailability', 'block_opencast'),
                        $description, 0));
                if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                    $additionalsettings->hide_if('block_opencast/addactivityepisodeavailability_' . $instance->id,
                        'block_opencast/addactivityepisodeenabled_' . $instance->id, 'notchecked');
                }
            }

            // Transcription upload settings.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/transcription_header_' . $instance->id,
                    get_string('transcriptionsettingsheader', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/transcriptionworkflow_' . $instance->id,
                    get_string('transcriptionworkflow', 'block_opencast'),
                    get_string('transcriptionworkflow_desc', 'block_opencast'), '', PARAM_TEXT));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/deletetranscriptionworkflow_' . $instance->id,
                    get_string('deletetranscriptionworkflow', 'block_opencast'),
                    get_string('deletetranscriptionworkflow_desc', 'block_opencast'), '', PARAM_TEXT));
            $additionalsettings->hide_if('block_opencast/deletetranscriptionworkflow_' . $instance->id,
                'block_opencast/transcriptionworkflow_' . $instance->id, 'eq', '');

            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/allowdownloadtranscription_' . $instance->id,
                    get_string('allowdownloadtranscriptionsetting', 'block_opencast'),
                    get_string('allowdownloadtranscriptionsetting_desc', 'block_opencast'), 1));
            $additionalsettings->hide_if('block_opencast/allowdownloadtranscription_' . $instance->id,
                'block_opencast/transcriptionworkflow_' . $instance->id, 'eq', '');

            $additionalsettings->add($transcriptionflavors);
            $additionalsettings->add(
                new admin_setting_configeditabletable(
                    'block_opencast/transcriptionflavorsoptions_' . $instance->id,
                    'transcriptionflavorsoptions_' . $instance->id,
                    get_string('addtranscriptionflavor', 'block_opencast')));

            $additionalsettings->hide_if('block_opencast/transcriptionflavorsoptions_' . $instance->id,
                'block_opencast/transcriptionworkflow_' . $instance->id, 'eq', '');

            $additionalsettings->add(new admin_setting_configtext('block_opencast/maxtranscriptionupload_' . $instance->id,
                new lang_string('maxtranscriptionupload', 'block_opencast'),
                get_string('maxtranscriptionupload_desc', 'block_opencast'), 3, PARAM_INT
            ));
            $additionalsettings->hide_if('block_opencast/maxtranscriptionupload_' . $instance->id,
                'block_opencast/transcriptionworkflow_' . $instance->id, 'eq', '');

            $additionalsettings->add(
                new admin_setting_filetypes('block_opencast/transcriptionfileextensions_' . $instance->id,
                    new lang_string('transcriptionfileextensions', 'block_opencast'),
                    get_string('transcriptionfileextensions_desc', 'block_opencast',
                        $CFG->wwwroot . '/admin/tool/filetypes/index.php')
                ));
            $additionalsettings->hide_if('block_opencast/transcriptionfileextensions_' . $instance->id,
                'block_opencast/transcriptionworkflow_' . $instance->id, 'eq', '');
            // End of transcription upload settings.
            // Live Status Update.
            // Setting for live status update for processing as well as uploading events.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/liveupdate_settingheader_' . $instance->id,
                    get_string('liveupdate_settingheader', 'block_opencast'),
                    ''));

            // Enables live status update here.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/liveupdateenabled_' . $instance->id,
                    get_string('liveupdate_settingenabled', 'block_opencast'),
                    get_string('liveupdate_settingenabled_desc', 'block_opencast'), 1));

            // Setting for reload timeout after an event has new changes.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/liveupdatereloadtimeout_' . $instance->id,
                    get_string('liveupdate_reloadtimeout', 'block_opencast'),
                    get_string('liveupdate_reloadtimeout_desc', 'block_opencast'), 3, PARAM_INT));
            $additionalsettings->hide_if('block_opencast/liveupdatereloadtimeout_' . $instance->id,
                'block_opencast/liveupdateenabled_' . $instance->id, 'notchecked');

            // Privacy notice display additional settings.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/swprivacynotice_header_' . $instance->id,
                    get_string('swprivacynotice_settingheader', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_confightmleditor('block_opencast/swprivacynoticeinfotext_' . $instance->id,
                    get_string('swprivacynotice_settinginfotext', 'block_opencast'),
                    get_string('swprivacynotice_settinginfotext_desc', 'block_opencast'), null));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/swprivacynoticewfds_' . $instance->id,
                    get_string('swprivacynotice_settingwfds', 'block_opencast'),
                    get_string('swprivacynotice_settingwfds_desc', 'block_opencast'), null));
            // Providing hide_if for this setting.
            $additionalsettings->hide_if('block_opencast/swprivacynoticewfds_' . $instance->id,
                'block_opencast/swprivacynoticeinfotext_' . $instance->id, 'eq', '');

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/swprivacynoticetitle_' . $instance->id,
                    get_string('swprivacynotice_settingtitle', 'block_opencast'),
                    get_string('swprivacynotice_settingtitle_desc', 'block_opencast'), null));
            // Providing hide_if for this setting.
            $additionalsettings->hide_if('block_opencast/swprivacynoticetitle_' . $instance->id,
                'block_opencast/swprivacynoticeinfotext_' . $instance->id, 'eq', '');
            // End of privacy notice.

            // Additional Settings.
            // Terms of use. Downlaod channel. Custom workflows channel. Support email.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/download_settingheader_' . $instance->id,
                    get_string('additional_settings', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/download_channel_' . $instance->id,
                    get_string('download_setting', 'block_opencast'),
                    get_string('download_settingdesc', 'block_opencast'), "api"));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/direct_access_channel_' . $instance->id,
                    get_string('directaccess_setting', 'block_opencast'),
                    get_string('directaccess_settingdesc', 'block_opencast'), ''));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/workflow_tags_' . $instance->id,
                    get_string('workflowtags_setting', 'block_opencast'),
                    get_string('workflowtags_settingdesc', 'block_opencast'), null));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/support_email_' . $instance->id,
                    get_string('support_setting', 'block_opencast'),
                    get_string('support_settingdesc', 'block_opencast'), null));

            $additionalsettings->add(new admin_setting_confightmleditor(
                'block_opencast/termsofuse_' . $instance->id,
                get_string('termsofuse', 'block_opencast'),
                get_string('termsofuse_desc', 'block_opencast'), null));

            // Settings page: LTI module features.
            $ltimodulesettings = new admin_settingpage('block_opencast_ltimodulesettings_' . $instance->id,
                get_string('ltimodule_settings', 'block_opencast'));
            $ADMIN->add($category, $ltimodulesettings);

            // Add LTI series modules section.
            $ltimodulesettings->add(
                new admin_setting_heading('block_opencast/addlti_settingheader_' . $instance->id,
                    get_string('addlti_settingheader', 'block_opencast'),
                    ''));

            // Add LTI series modules: Enable feature.
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltienabled_' . $instance->id,
                    get_string('addlti_settingenabled', 'block_opencast'),
                    get_string('addlti_settingenabled_desc', 'block_opencast'), 0));

            // Add LTI series modules: Default LTI series module title.
            $ltimodulesettings->add(
                new admin_setting_configtext('block_opencast/addltidefaulttitle_' . $instance->id,
                    get_string('addlti_settingdefaulttitle', 'block_opencast'),
                    get_string('addlti_settingdefaulttitle_desc', 'block_opencast'),
                    get_string('addlti_defaulttitle', 'block_opencast'),
                    PARAM_TEXT));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltidefaulttitle_' . $instance->id,
                    'block_opencast/addltienabled_' . $instance->id, 'notchecked');
            }

            // Add LTI series modules: Preconfigured LTI tool.
            $tools = ltimodulemanager::get_preconfigured_tools();
            // If there are any tools to be selected.
            if (count($tools) > 0) {
                $ltimodulesettings->add(
                    new admin_setting_configselect('block_opencast/addltipreconfiguredtool_' . $instance->id,
                        get_string('addlti_settingpreconfiguredtool', 'block_opencast'),
                        get_string('addlti_settingpreconfiguredtool_desc', 'block_opencast'),
                        null,
                        $tools));

                // If there aren't any preconfigured tools to be selected.
            } else {
                // Add an empty element to at least create the setting when the plugin is installed.
                // Additionally, show some information text where to add preconfigured tools.
                $url = new moodle_url('/admin/settings.php?section=modsettinglti');
                $link = html_writer::link($url, get_string('manage_tools', 'mod_lti'), ['target' => '_blank']);
                $description = get_string('addlti_settingpreconfiguredtool_notools', 'block_opencast', $link);
                $ltimodulesettings->add(
                    new admin_setting_configempty('block_opencast/addltipreconfiguredtool_' . $instance->id,
                        get_string('addlti_settingpreconfiguredtool', 'block_opencast'),
                        $description));
            }
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltipreconfiguredtool_' . $instance->id,
                    'block_opencast/addltienabled_' . $instance->id, 'notchecked');
            }

            // Add LTI series modules: Intro.
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltiintro_' . $instance->id,
                    get_string('addlti_settingintro', 'block_opencast'),
                    get_string('addlti_settingintro_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltiintro_' . $instance->id,
                    'block_opencast/addltienabled_' . $instance->id, 'notchecked');
            }

            // Add LTI series modules: Section.
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltisection_' . $instance->id,
                    get_string('addlti_settingsection', 'block_opencast'),
                    get_string('addlti_settingsection_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltisection_' . $instance->id,
                    'block_opencast/addltienabled_' . $instance->id, 'notchecked');
            }

            // Add LTI series modules: Availability.
            $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
            $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), ['target' => '_blank']);
            $description = get_string('addlti_settingavailability_desc', 'block_opencast') . '<br />' .
                get_string('addlti_settingavailability_note', 'block_opencast', $link);
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltiavailability_' . $instance->id,
                    get_string('addlti_settingavailability', 'block_opencast'),
                    $description, 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltiavailability_' . $instance->id,
                    'block_opencast/addltienabled_' . $instance->id, 'notchecked');
            }

            // Add LTI episode modules section.
            $ltimodulesettings->add(
                new admin_setting_heading('block_opencast/addltiepisode_settingheader_' . $instance->id,
                    get_string('addltiepisode_settingheader', 'block_opencast'),
                    ''));

            // Add LTI episode modules: Enable feature.
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltiepisodeenabled_' . $instance->id,
                    get_string('addltiepisode_settingenabled', 'block_opencast'),
                    get_string('addltiepisode_settingenabled_desc', 'block_opencast'), 0));

            // Add LTI episode modules: Preconfigured LTI tool.
            $tools = ltimodulemanager::get_preconfigured_tools();
            // If there are any tools to be selected.
            if (count($tools) > 0) {
                $ltimodulesettings->add(
                    new admin_setting_configselect('block_opencast/addltiepisodepreconfiguredtool_' . $instance->id,
                        get_string('addltiepisode_settingpreconfiguredtool', 'block_opencast'),
                        get_string('addltiepisode_settingpreconfiguredtool_desc', 'block_opencast'),
                        null,
                        $tools));

                // If there aren't any preconfigured tools to be selected.
            } else {
                // Add an empty element to at least create the setting when the plugin is installed.
                // Additionally, show some information text where to add preconfigured tools.
                $url = new moodle_url('/admin/settings.php?section=modsettinglti');
                $link = html_writer::link($url, get_string('manage_tools', 'mod_lti'), ['target' => '_blank']);
                $description = get_string('addltiepisode_settingpreconfiguredtool_notools', 'block_opencast', $link);
                $ltimodulesettings->add(
                    new admin_setting_configempty('block_opencast/addltiepisodepreconfiguredtool_' . $instance->id,
                        get_string('addltiepisode_settingpreconfiguredtool', 'block_opencast'),
                        $description));
            }
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltiepisodepreconfiguredtool_' . $instance->id,
                    'block_opencast/addltiepisodeenabled_' . $instance->id, 'notchecked');
            }

            // Add LTI episode modules: Intro.
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltiepisodeintro_' . $instance->id,
                    get_string('addltiepisode_settingintro', 'block_opencast'),
                    get_string('addltiepisode_settingintro_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltiepisodeintro_' . $instance->id,
                    'block_opencast/addltiepisodeenabled_' . $instance->id, 'notchecked');
            }

            // Add LTI episode modules: Section.
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltiepisodesection_' . $instance->id,
                    get_string('addltiepisode_settingsection', 'block_opencast'),
                    get_string('addltiepisode_settingsection_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltiepisodesection_' . $instance->id,
                    'block_opencast/addltiepisodeenabled_' . $instance->id, 'notchecked');
            }

            // Add LTI episode modules: Availability.
            $url = new moodle_url('/admin/settings.php?section=optionalsubsystems');
            $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), ['target' => '_blank']);
            $description = get_string('addltiepisode_settingavailability_desc', 'block_opencast') . '<br />' .
                get_string('addlti_settingavailability_note', 'block_opencast', $link);
            $ltimodulesettings->add(
                new admin_setting_configcheckbox('block_opencast/addltiepisodeavailability_' . $instance->id,
                    get_string('addltiepisode_settingavailability', 'block_opencast'),
                    $description, 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $ltimodulesettings->hide_if('block_opencast/addltiepisodeavailability_' . $instance->id,
                    'block_opencast/addltiepisodeenabled_' . $instance->id, 'notchecked');
            }

            // Settings page: Import videos features.
            $importvideossettings = new admin_settingpage('block_opencast_importvideossettings_' . $instance->id,
                get_string('importvideos_settings', 'block_opencast'));
            $ADMIN->add($category, $importvideossettings);

            // Import videos section.
            $importvideossettings->add(
                new admin_setting_heading('block_opencast/importvideos_settingheader_' . $instance->id,
                    get_string('importvideos_settingheader', 'block_opencast'),
                    ''));

            // Import videos: Enable feature.
            $importvideossettings->add(
                new admin_setting_configcheckbox('block_opencast/importvideosenabled_' . $instance->id,
                    get_string('importvideos_settingenabled', 'block_opencast'),
                    get_string('importvideos_settingenabled_desc', 'block_opencast'), 1));

            // Import Video: define modes (ACL Change / Duplicating Events).
            $importmodechoices = [
                'duplication' => get_string('importvideos_settingmodeduplication', 'block_opencast'),
                'acl' => get_string('importvideos_settingmodeacl', 'block_opencast'),
            ];

            // Set default to duplication mode.
            $select = new admin_setting_configselect('block_opencast/importmode_' . $instance->id,
                get_string('importmode', 'block_opencast'),
                get_string('importmodedesc', 'block_opencast'),
                'duplication', $importmodechoices);

            $importvideossettings->add($select);

            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $importvideossettings->hide_if('block_opencast/importmode_' . $instance->id,
                    'block_opencast/importvideosenabled_' . $instance->id, 'notchecked');
            }

            // Import videos: Duplicate workflow.
            // The default duplicate-event workflow has archive tag, therefore it needs to be adjusted here as well.
            // As this setting has used api tag for the duplicate event, it is now possible to have multiple tags in here.
            $workflowchoices = setting_helper::load_workflow_choices($instance->id, 'api,archive');
            if ($workflowchoices instanceof opencast_connection_exception ||
                $workflowchoices instanceof empty_configuration_exception) {
                $opencasterror = $workflowchoices->getMessage();
                $workflowchoices = [null => get_string('adminchoice_noconnection', 'block_opencast')];
            }
            $select = new admin_setting_configselect('block_opencast/duplicateworkflow_' . $instance->id,
                get_string('duplicateworkflow', 'block_opencast'),
                get_string('duplicateworkflowdesc', 'block_opencast'),
                null, $workflowchoices);

            if ($CFG->branch >= 310) { // The validation functionality for admin settings is not available before Moodle 3.10.
                $select->set_validate_function([setting_helper::class, 'validate_workflow_setting']);
            }

            $importvideossettings->add($select);

            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $importvideossettings->hide_if('block_opencast/duplicateworkflow_' . $instance->id,
                    'block_opencast/importvideosenabled_' . $instance->id, 'notchecked');
                $importvideossettings->hide_if('block_opencast/duplicateworkflow_' . $instance->id,
                    'block_opencast/importmode_' . $instance->id, 'eq', 'acl');
            }

            // Import videos: Enable import videos within Moodle core course import wizard feature.
            // This setting applies to both of import modes, therefore hide_if is only limited to importvideosenabled.
            $importvideossettings->add(
                new admin_setting_configcheckbox('block_opencast/importvideoscoreenabled_' . $instance->id,
                    get_string('importvideos_settingcoreenabled', 'block_opencast'),
                    get_string('importvideos_settingcoreenabled_desc', 'block_opencast'), 1));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $importvideossettings->hide_if('block_opencast/importvideoscoreenabled_' . $instance->id,
                    'block_opencast/importvideosenabled_' . $instance->id, 'notchecked');
            }

            // Import videos: Define a pre-defined configuration to enabled the import core settings.
            // This setting depends on the importvideoscoreenabled setting.
            $importvideoscorevaluechioces = [
                0 => get_string('importvideos_settingcoredefaultvalue_false', 'block_opencast'),
                1 => get_string('importvideos_settingcoredefaultvalue_true', 'block_opencast'), ];
            $defaultvaluechioce = 0;
            $importvideossettings->add(
                new admin_setting_configselect('block_opencast/importvideoscoredefaultvalue_' . $instance->id,
                    get_string('importvideos_settingcoredefaultvalue', 'block_opencast'),
                    get_string('importvideos_settingcoredefaultvalue_desc', 'block_opencast'),
                    $defaultvaluechioce, $importvideoscorevaluechioces));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $importvideossettings->hide_if('block_opencast/importvideoscoredefaultvalue_' . $instance->id,
                    'block_opencast/importvideoscoreenabled_' . $instance->id, 'notchecked');
            }

            // Import videos: Enable manual import videos feature.
            // This setting applies to both of import modes, therefore hide_if is only limited to importvideosenabled.
            $importvideossettings->add(
                new admin_setting_configcheckbox('block_opencast/importvideosmanualenabled_' . $instance->id,
                    get_string('importvideos_settingmanualenabled', 'block_opencast'),
                    get_string('importvideos_settingmanualenabled_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $importvideossettings->hide_if('block_opencast/importvideosmanualenabled_' . $instance->id,
                    'block_opencast/importvideosenabled_' . $instance->id, 'notchecked');
            }

            // Import videos: Handle Opencast series modules during manual import.
            $importvideossettings->add(
                new admin_setting_configcheckbox('block_opencast/importvideoshandleseriesenabled_' . $instance->id,
                    get_string('importvideos_settinghandleseriesenabled', 'block_opencast'),
                    get_string('importvideos_settinghandleseriesenabled_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $importvideossettings->hide_if('block_opencast/importvideoshandleseriesenabled_' . $instance->id,
                    'block_opencast/importvideosenabled_' . $instance->id, 'notchecked');
                $importvideossettings->hide_if('block_opencast/importvideoshandleseriesenabled_' . $instance->id,
                    'block_opencast/importmode_' . $instance->id, 'eq', 'acl');
                $importvideossettings->hide_if('block_opencast/importvideoshandleseriesenabled_' . $instance->id,
                    'block_opencast/duplicateworkflow_' . $instance->id, 'eq', '');
                $importvideossettings->hide_if('block_opencast/importvideoshandleseriesenabled_' . $instance->id,
                    'block_opencast/importvideosmanualenabled_' . $instance->id, 'notchecked');
            }

            // Import videos: Handle Opencast episode modules during manual import.
            $importvideossettings->add(
                new admin_setting_configcheckbox('block_opencast/importvideoshandleepisodeenabled_' . $instance->id,
                    get_string('importvideos_settinghandleepisodeenabled', 'block_opencast'),
                    get_string('importvideos_settinghandleepisodeenabled_desc', 'block_opencast'), 0));
            if ($CFG->branch >= 37) { // The hide_if functionality for admin settings is not available before Moodle 3.7.
                $importvideossettings->hide_if('block_opencast/importvideoshandleepisodeenabled_' . $instance->id,
                    'block_opencast/importvideosenabled_' . $instance->id, 'notchecked');
                $importvideossettings->hide_if('block_opencast/importvideoshandleepisodeenabled_' . $instance->id,
                    'block_opencast/importmode_' . $instance->id, 'eq', 'acl');
                $importvideossettings->hide_if('block_opencast/importvideoshandleepisodeenabled_' . $instance->id,
                    'block_opencast/duplicateworkflow_' . $instance->id, 'eq', '');
                $importvideossettings->hide_if('block_opencast/importvideoshandleepisodeenabled_' . $instance->id,
                    'block_opencast/importvideosmanualenabled_' . $instance->id, 'notchecked');
            }

            // Don't spam other setting pages with error messages just because the tree was built.
            if ($opencasterror && $PAGE->pagetype == 'admin-setting-block_opencast') {
                notification::error($opencasterror);
            }
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
