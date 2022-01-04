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
use block_opencast\admin_setting_hiddenhelpbtn;
use block_opencast\workflow_setting_helper;

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // Needs this condition or there is error on login page.
    global $CFG, $PAGE, $ADMIN, $OUTPUT;

    // Empty $settings to prevent a single settings page from being created by lib/classes/plugininfo/block.php
    // because we will create several settings pages now.


    $settings = null;

    // Create admin settings category.
    $settingscategory = new admin_category('block_opencast', new lang_string('settings', 'block_opencast'));
    $ADMIN->add('blocksettings', $settingscategory);

    $ocinstances = \tool_opencast\local\settings_api::get_ocinstances();

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

        // Create full settings page structure only if really needed.
    } else if ($ADMIN->fulltree) {
        if ($PAGE->state !== moodle_page::STATE_IN_BODY) {
            $PAGE->requires->css('/blocks/opencast/css/tabulator.min.css');
            $PAGE->requires->css('/blocks/opencast/css/tabulator_bootstrap4.min.css');
        }

        $sharedsettings = new admin_settingpage('block_opencast_sharedsettings', get_string('shared_settings', 'block_opencast'));
        $sharedsettings->add(
            new admin_setting_configtext('block_opencast/cachevalidtime',
                get_string('cachevalidtime', 'block_opencast'),
                get_string('cachevalidtime_desc', 'block_opencast'), 500, PARAM_INT));
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

            // Setup JS.
            $rolesdefault = '[{"rolename":"ROLE_ADMIN","actions":"write,read","permanent":1},' .
                '{"rolename":"ROLE_GROUP_MH_DEFAULT_ORG_EXTERNAL_APPLICATIONS","actions":"write,read","permanent":1},' .
                '{"rolename":"[COURSEID]_Instructor","actions":"write,read","permanent":1},' .
                '{"rolename":"[COURSEGROUPID]_Learner","actions":"read","permanent":0}]';

            $metadatadefault = '[' .
                '{"name":"title","datatype":"text","required":1,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}"},' .
                '{"name":"subjects","datatype":"autocomplete","required":0,"readonly":0,"param_json":null},' .
                '{"name":"description","datatype":"textarea","required":0,"readonly":0,"param_json":' .
                '"{\"rows\":\"3\",\"cols\":\"19\"}"},' .
                '{"name":"language","datatype":"select","required":0,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
                '\"slv\":\"Slovenian\",\"por\":\"Portugese\",\"roh\":\"Romansh\",\"ara\":\"Arabic\",\"pol\":\"Polish\",\"ita\":' .
                '\"Italian\",\"zho\":\"Chinese\",\"fin\":\"Finnish\",\"dan\":\"Danish\",\"ukr\":\"Ukrainian\",\"fra\":\"French\",' .
                '\"spa\":\"Spanish\",\"gsw\":\"Swiss German\",\"nor\":\"Norwegian\",\"rus\":\"Russian\",\"jpx\":\"Japanese\",' .
                '\"nld\":\"Dutch\",\"tur\":\"Turkish\",\"hin\":\"Hindi\",\"swa\":\"Swedish\",' .
                '\"eng\":\"English\",\"deu\":\"German\"}"},' .
                '{"name":"rightsHolder","datatype":"text","required":0,"readonly":0,"param_json":' .
                '"{\"style\":\"min-width: 27ch;\"}"},' .
                '{"name":"license","datatype":"select","required":0,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
                '\"ALLRIGHTS\":\"All Rights Reserved\",\"CC0\":\"CC0\",\"CC-BY-ND\":\"CC BY-ND\",\"CC-BY-NC-ND\":\"CC BY-NC-ND\",' .
                '\"CC-BY-NC-SA\":\"CC BY-NC-SA\",\"CC-BY-SA\":\"CC BY-SA\",\"CC-BY-NC\":\"CC BY-NC\",\"CC-BY\":\"CC BY\"}"},' .
                '{"name":"creator","datatype":"autocomplete","required":0,"readonly":0,"param_json":null},' .
                '{"name":"contributor","datatype":"autocomplete","required":0,"readonly":0,"param_json":null}]';

            $metadataseriesdefault = '[' .
                '{"name":"title","datatype":"text","required":1,"readonly":0,"param_json":"{\"style\":\"min-width: 27ch;\"}"},' .
                '{"name":"subjects","datatype":"autocomplete","required":0,"readonly":0,"param_json":null},' .
                '{"name":"description","datatype":"textarea","required":0,"readonly":0,"param_json":' .
                '"{\"rows\":\"3\",\"cols\":\"19\"}"},' .
                '{"name":"language","datatype":"select","required":0,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
                '\"slv\":\"Slovenian\",\"por\":\"Portugese\",\"roh\":\"Romansh\",\"ara\":\"Arabic\",\"pol\":\"Polish\",\"ita\":' .
                '\"Italian\",\"zho\":\"Chinese\",\"fin\":\"Finnish\",\"dan\":\"Danish\",\"ukr\":\"Ukrainian\",\"fra\":\"French\",' .
                '\"spa\":\"Spanish\",\"gsw\":\"Swiss German\",\"nor\":\"Norwegian\",\"rus\":\"Russian\",\"jpx\":\"Japanese\",' .
                '\"nld\":\"Dutch\",\"tur\":\"Turkish\",\"hin\":\"Hindi\",\"swa\":\"Swedish\",' .
                '\"eng\":\"English\",\"deu\":\"German\"}"},' .
                '{"name":"rightsHolder","datatype":"text","required":1,"readonly":0,"param_json":' .
                '"{\"style\":\"min-width: 27ch;\"}"},' .
                '{"name":"license","datatype":"select","required":1,"readonly":0,"param_json":"{\"\":\"No option selected\",' .
                '\"ALLRIGHTS\":\"All Rights Reserved\",\"CC0\":\"CC0\",\"CC-BY-ND\":\"CC BY-ND\",\"CC-BY-NC-ND\":\"CC BY-NC-ND\",' .
                '\"CC-BY-NC-SA\":\"CC BY-NC-SA\",\"CC-BY-SA\":\"CC BY-SA\",\"CC-BY-NC\":\"CC BY-NC\",\"CC-BY\":\"CC BY\"}"},' .
                '{"name":"creator","datatype":"autocomplete","required":0,"readonly":0,"param_json":null},' .
                '{"name":"contributor","datatype":"autocomplete","required":0,"readonly":0,"param_json":null}]';

            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpname_' . $instance->id,
                'helpbtnname_' . $instance->id, 'descriptionmdfn', 'block_opencast'));
            $generalsettings->add(new admin_setting_hiddenhelpbtn('block_opencast/hiddenhelpparams_' . $instance->id,
                'helpbtnparams_' . $instance->id, 'catalogparam', 'block_opencast'));

            $rolessetting = new admin_setting_configtext('block_opencast/roles_' . $instance->id,
                get_string('aclrolesname', 'block_opencast'),
                get_string('aclrolesnamedesc',
                    'block_opencast'), $rolesdefault);

            $metadatasetting = new admin_setting_configtext('block_opencast/metadata_' . $instance->id,
                get_string('metadata', 'block_opencast'),
                get_string('metadatadesc',
                    'block_opencast'), $metadatadefault);

            $metadataseriessetting = new admin_setting_configtext('block_opencast/metadataseries_' . $instance->id,
                get_string('metadataseries', 'block_opencast'),
                get_string('metadataseriesdesc',
                    'block_opencast'), $metadataseriesdefault);

            // Crashes if plugins.php is opened because css cannot be included anymore.
            if ($PAGE->state !== moodle_page::STATE_IN_BODY) {
                $PAGE->requires->js_call_amd('block_opencast/block_settings', 'init', [
                    $rolessetting->get_id(),
                    $metadatasetting->get_id(),
                    $metadataseriessetting->get_id(),
                    $instance->id
                ]);
            }

            $generalsettings->add(
                new admin_setting_heading('block_opencast/general_settings_' . $instance->id,
                    get_string('cronsettings', 'block_opencast'),
                    ''));

            $url = new moodle_url('/admin/tool/task/scheduledtasks.php');
            $link = html_writer::link($url, get_string('pluginname', 'tool_task'), array('target' => '_blank'));
            $generalsettings->add(
                new admin_setting_configtext('block_opencast/limituploadjobs_' . $instance->id,
                    get_string('limituploadjobs', 'block_opencast'),
                    get_string('limituploadjobsdesc', 'block_opencast', $link), 1, PARAM_INT));

            $workflowchoices = workflow_setting_helper::load_workflow_choices($instance->id, 'upload');
            if ($workflowchoices instanceof \block_opencast\opencast_connection_exception ||
                $workflowchoices instanceof \tool_opencast\empty_configuration_exception) {
                $opencasterror = $workflowchoices->getMessage();
                $workflowchoices = [null => get_string('adminchoice_noconnection', 'block_opencast')];
            }

            $generalsettings->add(new admin_setting_configselect('block_opencast/uploadworkflow_' . $instance->id,
                get_string('uploadworkflow', 'block_opencast'),
                get_string('uploadworkflowdesc', 'block_opencast'),
                'ng-schedule-and-upload', $workflowchoices
            ));

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


            $workflowchoices = workflow_setting_helper::load_workflow_choices($instance->id, 'delete');
            if ($workflowchoices instanceof \block_opencast\opencast_connection_exception ||
                $workflowchoices instanceof \tool_opencast\empty_configuration_exception) {
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


            $workflowchoices = workflow_setting_helper::load_workflow_choices($instance->id, 'archive');
            if ($workflowchoices instanceof \block_opencast\opencast_connection_exception ||
                $workflowchoices instanceof \tool_opencast\empty_configuration_exception) {
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

                $sizelist = array(-1, 53687091200, 21474836480, 10737418240, 5368709120, 2147483648, 1610612736, 1073741824,
                    536870912, 268435456, 134217728, 67108864);
                $filesizes = array();
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

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/lticonsumerkey_' . $instance->id,
                    get_string('lticonsumerkey', 'block_opencast'),
                    get_string('lticonsumerkey_desc', 'block_opencast'), ""));

            $additionalsettings->add(
                new admin_setting_configpasswordunmask('block_opencast/lticonsumersecret_' . $instance->id,
                    get_string('lticonsumersecret', 'block_opencast'),
                    get_string('lticonsumersecret_desc', 'block_opencast'), ""));

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

            // LTI Consumer Key for the editor alone.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/editorlticonsumerkey_' . $instance->id,
                    get_string('editorlticonsumerkey', 'block_opencast'),
                    get_string('editorlticonsumerkey_desc', 'block_opencast'), ""));

            // LTI Consumer Secret for the editor alone.
            $additionalsettings->add(
                new admin_setting_configpasswordunmask('block_opencast/editorlticonsumersecret_' . $instance->id,
                    get_string('editorlticonsumersecret', 'block_opencast'),
                    get_string('editorlticonsumersecret_desc', 'block_opencast'), ""));

            // Opencast Video Player in additional feature settings.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/opencast_access_video_file_' . $instance->id,
                    get_string('opencaststaticvideofilelink', 'block_opencast'),
                    ''));

            // The Generall player Permission.
            $additionalsettings->add(
                new admin_setting_configcheckbox('block_opencast/enable_opencast_access_video_file_link_' . $instance->id,
                    get_string('enableopencaststaticvideofilelink', 'block_opencast'),
                    get_string('enableopencaststaticvideofilelink_desc', 'block_opencast'), 0));

            // LTI Consumer Key for the video player.
            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/staticvideofilelticonsumerkey_' . $instance->id,
                    get_string('staticvideofilelticonsumerkey', 'block_opencast'),
                    get_string('staticvideofilelticonsumerkey_desc', 'block_opencast'), ""));

            // LTI Consumer Secret for the video player.
            $additionalsettings->add(
                new admin_setting_configpasswordunmask('block_opencast/staticvideofilelticonsumersecret_' . $instance->id,
                    get_string('staticvideofilelticonsumersecret', 'block_opencast'),
                    get_string('staticvideofilelticonsumersecret_desc', 'block_opencast'), ""));

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
                $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
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
                $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
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

            // Additional Settings.
            // Terms of use. Downlaod channel. Custom workflows channel. Support email.
            $additionalsettings->add(
                new admin_setting_heading('block_opencast/download_settingheader_' . $instance->id,
                    get_string('additional_settings', 'block_opencast'),
                    ''));

            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/download_channel_' . $instance->id,
                    get_string('download_setting', 'block_opencast'),
                    get_string('download_settingdesc', 'block_opencast'), "lms-download"));


            $additionalsettings->add(
                new admin_setting_configtext('block_opencast/workflow_tag_' . $instance->id,
                    get_string('workflowtag_setting', 'block_opencast'),
                    get_string('workflowtag_settingdesc', 'block_opencast'), null));

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
            $tools = \block_opencast\local\ltimodulemanager::get_preconfigured_tools();
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
                $url = '/admin/settings.php?section=modsettinglti';
                $link = html_writer::link($url, get_string('manage_tools', 'mod_lti'), array('target' => '_blank'));
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
            $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
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
            $tools = \block_opencast\local\ltimodulemanager::get_preconfigured_tools();
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
                $url = '/admin/settings.php?section=modsettinglti';
                $link = html_writer::link($url, get_string('manage_tools', 'mod_lti'), array('target' => '_blank'));
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
            $link = html_writer::link($url, get_string('advancedfeatures', 'admin'), array('target' => '_blank'));
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
            $importmodechoices = array(
                'duplication' => get_string('importvideos_settingmodeduplication', 'block_opencast'),
                'acl' => get_string('importvideos_settingmodeacl', 'block_opencast')
            );

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
            $workflowchoices = workflow_setting_helper::load_workflow_choices($instance->id, 'api');
            if ($workflowchoices instanceof \block_opencast\opencast_connection_exception ||
                $workflowchoices instanceof \tool_opencast\empty_configuration_exception) {
                $opencasterror = $workflowchoices->getMessage();
                $workflowchoices = [null => get_string('adminchoice_noconnection', 'block_opencast')];
            }
            $select = new admin_setting_configselect('block_opencast/duplicateworkflow_' . $instance->id,
                get_string('duplicateworkflow', 'block_opencast'),
                get_string('duplicateworkflowdesc', 'block_opencast'),
                null, $workflowchoices);

            if ($CFG->branch >= 310) { // The validation functionality for admin settings is not available before Moodle 3.10.
                $select->set_validate_function([workflow_setting_helper::class, 'validate_workflow_setting']);
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
                \core\notification::error($opencasterror);
            }
        }
    }
}
$settings = null;
