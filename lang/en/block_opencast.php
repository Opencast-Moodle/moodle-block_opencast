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
 * Strings for component 'block_opencast', language 'en'
 *
 * @package    block_opencast
 * @copyright  2017 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aclgroupdeleted'] = 'Access deleted for video: {$a->title}';
$string['aclrolesadded'] = 'The change of visibility has been triggered to allow all students of the course to access the video: {$a->title}<br />Please refresh the site after some time to see the current visibility status.';
$string['aclroleschangeerror'] = 'Error during the change of visibility of the video: {$a->title}<br />Some changes might have not been saved. If this occurs repeatedly, please contact your support team.';
$string['aclrolesdeleted'] = 'The change of visibility has been triggered to prevent all students of the course from accessing the video: {$a->title}<br />Please refresh the site after some time to see the current visibility status.';
$string['aclrolesaddedgroup'] = 'The change of visibility has been triggered to allow students of selected groups to access the video: {$a->title}<br />Please refresh the site after some time to see the current visibility status.';
$string['acl_settingcontrolafter'] = 'Allow episode visibility control after processing';
$string['acl_settingcontrolafter_desc'] = 'If enabled, teachers can control the visibility of an Opencast episode after the episode has been processed in Opencast.';
$string['acl_settingcontrolgroup'] = 'Allow episode group restriction';
$string['acl_settingcontrolgroup_desc'] = 'If enabled, teachers can not only control the visibility of an Opencast episode for all course users but can also restrict the visibility to particular course groups.';
$string['acl_settingheader'] = 'Control episode visibility';
$string['acl_settingcontrolwaitingtime'] = 'Waiting time for scheduled visibility change (Minutes)';
$string['acl_settingcontrolwaitingtime_desc'] = 'It defines a minimum waiting time (in minutes) that scheduled visibility change process should wait. This time span will be added to current time for the scheduled video change date filed in add video form, it will also be used to validate that field. Based on how fast the Opencast instance processes the videos, this waiting time could be configured. <br />NOTE: When empty or (zero) "0", the default value is used.';
$string['aclnothingtobesaved'] = 'No changes to the visibility have been made.';
$string['accesspolicies'] = 'Access Policies';
$string['aclrolesname'] = 'Roles';
$string['aclrolesnamedesc'] = 'You can use placeholders in the role name which are automatically replaced (<a target="_blank" href="https://moodle.docs.opencast.org/#block/general_settings/#placeholders">list of placeholders</a>). The list of actions must be comma-separated.';
$string['actions'] = 'Comma-separated list of actions';
$string['addcatalog'] = 'Add new metadata';
$string['addnewcatalogfield'] = 'A new field has been added to metadata catalog.';
$string['adhocfiledeletion'] = 'Delete videofile from moodle';
$string['adhocfiledeletiondesc'] = 'If activated the plugin tries to delete the videofile from moodle\'s filessystem right after it was uploaded to opencast server.
    Please note that the file will still remain in the file system, if it is used within other places in moodle.';
$string['addlti_modulecreated'] = 'The \'{$a}\' series module has been added to this course.';
$string['addlti_moduleexists'] = 'There is already an Opencast series module added to this course. There is no need to add another one.';
$string['addlti_modulenotcreated'] = 'The \'{$a}\' series module could not be created. Please try again or contact your Moodle administrator.';
$string['addlti_addbuttonexplanation'] = 'The videos which are added to and are available in this course are not provided to your students automatically.<br />Here, you can add an Opencast LTI series module to your course which provides all available videos as an Opencast series list to your students.';
$string['addlti_addbuttontitle'] = 'Add Opencast LTI series module to course';
$string['addlti_addbuttontitlereturncourse'] = 'Add module and return to course';
$string['addlti_addbuttontitlereturnoverview'] = 'Add module and return to overview';
$string['addlti_defaulttitle'] = 'Opencast videos';
$string['addlti_errornotenabledorworking'] = 'The \'Add Opencast series module\' feature is not enabled or not working';
$string['addlti_formltiavailability'] = 'Opencast series module access restrictions';
$string['addlti_formltiintro'] = 'Opencast series module intro';
$string['addlti_formltisection'] = 'Opencast series module target section';
$string['addlti_formltititle'] = 'Opencast series module title';
$string['addlti_header'] = 'Provide videos (LTI)';
$string['addlti_noemptytitle'] = 'You have to set a title for the Opencast series module or to use the default title ("{$a}")';
$string['addlti_settingavailability'] = 'Set series module availability';
$string['addlti_settingavailability_desc'] = 'If enabled, teachers can set the availability conditions when a new Opencast LTI series module is added to a course.';
$string['addlti_settingavailability_note'] = 'Please note: This feature is only available if availability is globally enabled on the {$a} admin setting page.';
$string['addlti_settingdefaulttitle'] = 'Default LTI series module title';
$string['addlti_settingdefaulttitle_desc'] = 'The default title to be used when a new Opencast LTI series module is added to a course.';
$string['addlti_settingintro'] = 'Add series module intro';
$string['addlti_settingintro_desc'] = 'If enabled, teachers can add an intro to the Opencast LTI series module. This intro will be shown on the course overview page.';
$string['addlti_settingenabled'] = 'Enable ”Add LTI series module"';
$string['addlti_settingenabled_desc'] = 'If enabled, teachers can add an Opencast LTI series module to a course. This LTI series module will be pointing to the course\'s Opencast series.';
$string['addlti_settingheader'] = 'Add Opencast LTI series modules to courses';
$string['addlti_settingpreconfiguredtool'] = 'Preconfigured LTI tool for series modules';
$string['addlti_settingpreconfiguredtool_desc'] = 'The preconfigured LTI tool to be used when a new Opencast LTI series module is added to a course.';
$string['addlti_settingpreconfiguredtool_notools'] = 'No preconfigured LTI tools to be used found. Please create an Opencast series LTI tool first on the {$a} admin setting page.';
$string['addlti_settingsection'] = 'Choose series module section';
$string['addlti_settingsection_desc'] = 'If enabled, teachers can choose the section which the Opencast LTI series module will be added to.';
$string['addlti_viewbuttonexplanation'] = 'An Opencast series module to provide the videos available in this course has been added to the course before.';
$string['addlti_viewbuttontitle'] = 'View Opencast LTI series module in course';
$string['addltiepisode_addicontitle'] = 'Add Opencast episode module to course';
$string['addltiepisode_addbuttontitlereturncourse'] = 'Add module and return to course';
$string['addltiepisode_addbuttontitlereturnoverview'] = 'Add module and return to overview';
$string['addltiepisode_explanation'] = 'Additionally, in the videos table above, you can add individual Opencast episode modules to your course which provide one single video activity to your students.';
$string['addltiepisode_defaulttitle'] = 'Opencast episode';
$string['addltiepisode_errornotenabledorworking'] = 'The \'Add Opencast episode module\' feature is not enabled or not working';
$string['addltiepisode_errorepisodeuuidnotvalid'] = 'The given episode UUID is not valid';
$string['addltiepisode_formltiavailability'] = 'Opencast episode module access restrictions';
$string['addltiepisode_formltiintro'] = 'Opencast episode module intro';
$string['addltiepisode_formltisection'] = 'Opencast episode module target section';
$string['addltiepisode_formltititle'] = 'Opencast episode module title';
$string['addltiepisode_modulecreated'] = 'The \'{$a}\' episode module has been added to this course.';
$string['addltiepisode_moduleexists'] = 'There is already an Opencast episode module added to this course. There is no need to add another one.';
$string['addltiepisode_modulenotcreated'] = 'The \'{$a}\' episode module could not be created. Please try again or contact your Moodle administrator.';
$string['addltiepisode_noemptytitle'] = 'You have to set a title for the Opencast episode module or to use the default title ("{$a}")';
$string['addltiepisode_settingavailability'] = 'Set episode module availability';
$string['addltiepisode_settingavailability_desc'] = 'If enabled, teachers can set the availability conditions when a new Opencast LTI episode module is added to a course.';
$string['addltiepisode_settingintro'] = 'Add episode module intro';
$string['addltiepisode_settingintro_desc'] = 'If enabled, teachers can add a intro to the Opencast LTI episode module. This intro will be shown on the course overview page.';
$string['addltiepisode_settingenabled'] = 'Enable ”Add LTI episode module"';
$string['addltiepisode_settingenabled_desc'] = 'If enabled, teachers can add an Opencast LTI episode module to a course. This LTI episode module will be pointing to an Opencast episode.';
$string['addltiepisode_settingheader'] = 'Add Opencast LTI episode modules to courses';
$string['addltiepisode_settingpreconfiguredtool'] = 'Preconfigured LTI tool for episode modules';
$string['addltiepisode_settingpreconfiguredtool_desc'] = 'The preconfigured LTI tool to be used when a new Opencast LTI episode module is added to a course.';
$string['addltiepisode_settingpreconfiguredtool_notools'] = 'No preconfigured LTI tools to be used found. Please create an Opencast episode LTI tool first on the {$a} admin setting page.';
$string['addltiepisode_settingsection'] = 'Choose episode module section';
$string['addltiepisode_settingsection_desc'] = 'If enabled, teachers can choose the section which the Opencast LTI episode module will be added to.';
$string['addltiepisode_viewicontitle'] = 'View Opencast episode module in course';

$string['addactivity_modulecreated'] = 'The \'{$a}\' series module has been added to this course.';
$string['addactivity_moduleexists'] = 'There is already an Opencast series module added to this course. There is no need to add another one.';
$string['addactivity_modulenotcreated'] = 'The \'{$a}\' series module could not be created. Please try again or contact your Moodle administrator.';
$string['addactivity_addbuttonexplanation'] = 'The videos which are added to and are available in this course are not provided to your students automatically.<br />Using the "Provide" buttons, you can add activities to your course which provide either all videos in a series or single videos to your students.';
$string['addactivity_addbuttontitle'] = 'Add Opencast Video Provider activity to course';
$string['addactivity_addbuttontitlereturncourse'] = 'Add module and return to course';
$string['addactivity_addbuttontitlereturnoverview'] = 'Add module and return to overview';
$string['addactivity_defaulttitle'] = 'Opencast videos';
$string['addactivity_formactivityavailability'] = 'Opencast series module access restrictions';
$string['addactivity_formactivityintro'] = 'Opencast series module intro';
$string['addactivity_formactivitysection'] = 'Opencast series module target section';
$string['addactivity_formactivitytitle'] = 'Opencast series module title';
$string['addactivity_header'] = 'Provide videos (Embedded)';
$string['addactivity_noemptytitle'] = 'You have to set a title for the Opencast series module or to use the default title ("{$a}")';
$string['addactivity_settingavailability'] = 'Set series module availability';
$string['addactivity_settingavailability_desc'] = 'If enabled, teachers can set the availability conditions when a new Opencast Video Provider activity for series is added to a course.';
$string['addactivity_settingavailability_note'] = 'Please note: This feature is only available if availability is globally enabled on the {$a} admin setting page.';
$string['addactivity_settingdefaulttitle'] = 'Default Opencast activity series module title';
$string['addactivity_settingdefaulttitle_desc'] = 'The default title to be used when a new Opencast Video Provider activity for series is added to a course.';
$string['addactivity_settingintro'] = 'Add series module intro';
$string['addactivity_settingintro_desc'] = 'If enabled, teachers can add an intro to the Opencast activity series module. This intro will be shown on the course overview page.';
$string['addactivity_settingenabled'] = 'Enable ”Add Opencast activity series module"';
$string['addactivity_settingenabled_desc'] = 'If enabled, teachers can add an Opencast Activity series module to a course. This Activity series module will be pointing to the course\'s Opencast series.';
$string['addactivity_settingheader'] = 'Add Opencast Activity modules to courses';
$string['addactivity_settingsection'] = 'Choose series module section';
$string['addactivity_settingsection_desc'] = 'If enabled, teachers can choose the section which the Opencast Activity series module will be added to.';
$string['addactivity_viewbuttonexplanation'] = 'An Opencast series module to provide the videos available in this course has been added to the course before.';
$string['addactivity_viewbuttontitle'] = 'View Opencast series module in course';
$string['addactivityepisode_addicontitle'] = 'Add Opencast episode module to course';
$string['addactivityepisode_addbuttontitlereturncourse'] = 'Add module and return to course';
$string['addactivityepisode_addbuttontitlereturnoverview'] = 'Add module and return to overview';
$string['addactivityepisode_explanation'] = 'Additionally, in the videos table above, you can add individual Opencast episode modules to your course which provide one single video activity to your students.';
$string['addactivityepisode_defaulttitle'] = 'Opencast episode';
$string['addactivityepisode_formactivityavailability'] = 'Opencast episode module access restrictions';
$string['addactivityepisode_formactivityintro'] = 'Opencast episode module intro';
$string['addactivityepisode_formactivitysection'] = 'Opencast episode module target section';
$string['addactivityepisode_formactivitytitle'] = 'Opencast episode module title';
$string['addactivityepisode_modulecreated'] = 'The \'{$a}\' episode module has been added to this course.';
$string['addactivityepisode_moduleexists'] = 'There is already an Opencast episode module added to this course. There is no need to add another one.';
$string['addactivityepisode_modulenotcreated'] = 'The \'{$a}\' episode module could not be created. Please try again or contact your Moodle administrator.';
$string['addactivityepisode_noemptytitle'] = 'You have to set a title for the Opencast episode module or to use the default title ("{$a}")';
$string['addactivityepisode_settingavailability'] = 'Set episode module availability';
$string['addactivityepisode_settingavailability_desc'] = 'If enabled, teachers can set the availability conditions when a new Opencast Activity episode module is added to a course.';
$string['addactivityepisode_settingintro'] = 'Add episode module intro';
$string['addactivityepisode_settingintro_desc'] = 'If enabled, teachers can add a intro to the Opencast Activity episode module. This intro will be shown on the course overview page.';
$string['addactivityepisode_settingenabled'] = 'Enable ”Add Opencast Activity episode module"';
$string['addactivityepisode_settingenabled_desc'] = 'If enabled, teachers can add an Opencast Video Provider activity for episodes to a course. This Opencast Activity episode module will be pointing to an Opencast episode.';
$string['addactivityepisode_settingsection'] = 'Choose episode module section';
$string['addactivityepisode_settingsection_desc'] = 'If enabled, teachers can choose the section which the Opencast Activity episode module will be added to.';
$string['addactivityepisode_viewicontitle'] = 'View Opencast episode module in course';
$string['additional_settings'] = 'Additional features';
$string['addvideo'] = 'Add video';
$string['addrole'] = 'Add new role';
$string['adminchoice_noworkflow'] = "-- No workflow --";
$string['adminchoice_noconnection'] = "-- Workflows could not be retrieved --";
$string['allowunassign'] = 'Allow unassign from course';
$string['allowunassigndesc'] = 'Delete the assignment of a course series to control visibility in filepicker and course lists. This feature is only available,
    when it is possible to have events without series in opencast. Please ask the admistrator of the opencast system before activating this.';
$string['appearance_settings'] = 'Appearance';
$string['appearance_overview_settingheader'] = 'Overview page';
$string['appearance_overview_settingshowenddate'] = 'Show end date';
$string['appearance_overview_settingshowenddate_desc'] = 'If enabled, the table of available videos on the overview page will contain a column which shows the end date of the Opencast episode.';
$string['appearance_overview_settingshowlocation'] = 'Show location';
$string['appearance_overview_settingshowlocation_desc'] = 'If enabled, the table of available videos on the overview page will contain a column which shows the location of the Opencast episode.';
$string['appearance_overview_settingshowpublicationchannels'] = 'Show publication channels';
$string['appearance_overview_settingshowpublicationchannels_desc'] = 'If enabled, the table of available videos on the overview page will contain a column which shows the publication channel of the Opencast episode. The same information will also be given on the Opencast episode deletion page.';

$string['backupopencastvideos'] = 'Include videos from Opencast instance {$a} in this course';
$string['blocksettings'] = 'Settings for a block instance';

$string['cachedef_videodata'] = 'Caches the result of the opencast api for the opencast-block.';
$string['cachevalidtime'] = 'Cache valid time';
$string['cachevalidtime_desc'] = 'Time in seconds, before the cache for the video data of each course is refreshed.';
$string['cantdeletedefaultseries'] = 'You cannot delete the default series. Please choose another default series before deleting this series.';
$string['catalog_static_params_empty'] = "Read only fields need to define a text in the parameters field.";
$string['catalog_params_noarray'] = "Parameters have to be either empty or a JSON representation of an array or an object.";
$string['changevisibility_visible'] = 'The video is visible to all students of the course. Click to alter visibility.';
$string['legendtitle'] = 'Details Table';
$string['legendalt'] = 'More details about all possible values of this column.';
$string['legendocstatecapturingdesc'] = 'Live capturing in progress.';
$string['legendocstatefaileddesc'] = 'Something went wrong while processing this video, and it has failed. Please contact the support/administrator in order to resolve this issue.';
$string['legendocstateneedscuttingdesc'] = 'The Video requires further attention in order to finish the processing stage.';
$string['legendocstateprocessingdesc'] = 'The video is uploaded, and it is being processed by Opencast.';
$string['legendocstatesucceededdesc'] = 'The video has been processed and it is ready.';
$string['legendvisibility_visible'] = 'Visible';
$string['legendvisibility_visibledesc'] = 'The video is visible to all students of the course.';
$string['changevisibility_mixed'] = 'The visibility of the video is in an invalid status. Click to choose the correct visibility.';
$string['legendvisibility_mixed'] = 'Invalid';
$string['legendvisibility_mixeddesc'] = 'The visibility of the video is in an invalid status.';
$string['changevisibility_hidden'] = 'The video is visible to no student. Click to alter visibility.';
$string['legendvisibility_hidden'] = 'Hidden';
$string['legendvisibility_hiddendesc'] = 'The video is not visible to any student.';
$string['changevisibility_group'] = 'The video is visible to all student belonging to selected groups. Click to alter visibility.';
$string['legendvisibility_group'] = 'Group Visibilty';
$string['legendvisibility_groupdesc'] = 'The video is visible to all student belonging to selected groups.';
$string['changevisibility_header'] = 'Change visibility for {$a->title}';
$string['changevisibility'] = 'Alter visibility';
$string['connection_failure'] = 'Could not reach Opencast server.';
$string['coursefullnameunknown'] = 'Unkown coursename';
$string['createdby'] = 'Uploaded by';
$string['createseriesforcourse'] = 'Create new series';
$string['cronsettings'] = 'Settings for upload jobs';
$string['catalogparam'] = 'Parameters in JSON-Format';
$string['catalogparam_help'] = '<b>JSON format:</b> {"param1":"value1", "param2":"value2"}<br>
                                <b>String (text), Long Text (textarea):</b> Parameters will be defined as attributes of the element. i.e. {"style":"min-width: 27ch;"} which defines the element´s style attribute <br>
                                <b>Drop Down (select):</b> Parameters will be defined as options of the select element. i.e. {"en": "English", "de": "German"} which takes the left side as value and right side as text to show<br>
                                <b>Arrays (autocomplete):</b> Parameters will be defined as <a target="_blank" href="https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#autocomplete">suggestions</a>. i.e. {"1": "Dr. Doe", "2": "Johnson"} which shows (Dr. Doe and Johnson) as suggestions<br>
                                <b>Date Time Selector (datetime):</b> Parameters will be defined as <a target="_blank" href="https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#date_selector">date_selector variables</a> . i.e. {"startyear": "1990", "stopyear": "2020"} which defines date range to be selected between 1990 - 2020';
$string['contributor'] = 'Contributor(s)';
$string['created'] = 'Created at';
$string['creator'] = 'Presenter(s)';
$string['descriptionmdfd'] = 'Field Description';
$string['descriptionmdfd_help'] = 'The content of this field is presented as a help icon near the metadata field.';

$string['date'] = 'Start Date';
$string['descriptionmdfn'] = 'Field name';
$string['descriptionmdfn_help'] = 'This is the actual field name passing as metadata (id); the presented name according to this field name should be set in language string.';
$string['descriptionmdpj'] = 'The value should be JSON string format and it is used to define parameters for the field!';
$string['default'] = 'Default';
$string['deleteaclgroup'] = 'Delete video from this list.';
$string['delete_confirm_role'] = 'Are you sure you want to delete this role?';
$string['delete_confirm_metadata'] = 'Are you sure you want to delete this metadata field?';
$string['delete_confirm_series'] = 'Are you sure you want to delete this series? <br/><b>Notice:</b> The series is only unlinked from this course but not deleted in Opencast.';
$string['deleteevent'] = 'Delete an event in Opencast';
$string['deleteeventdesc'] = 'You are about to delete this video permanently and irreversibly from Opencast.<br />All embedded links to it will become invalid. Please do not continue unless you are absolutely sure.';
$string['deletedraft'] = 'Delete a video before transfer to Opencast';
$string['deletedraftdesc'] = 'You are about to delete this video before the transfer to Opencast.<br />It will be removed from the transfer queue and will not be processed. Please do not continue unless you are absolutely sure.';
$string['deletegroupacldesc'] = 'You are about to delete the access to this video from this course.<br />If the access is deleted, the video is not displayed in filepicker and in the list of available videos. This does not affect videos, which are already embedded.<br />The video will not be deleted in Opencast.';
$string['deleteworkflow'] = 'Workflow to start before event is be deleted';
$string['deleteworkflowdesc'] = 'Before deleting a video, a workflow can be defined, which is called to retract the event from all publication channels.';
$string['delete_role'] = 'Delete role';
$string['delete_metadata'] = 'Delete metadata field';
$string['delete_series'] = 'Delete series';
$string['delete_series_failed'] = 'Deleting the series failed. Please try again later or contact an administrator.';
$string['deleting'] = 'Going to be deleted';
$string['description'] = 'Description';
$string['duration'] = 'Duration';
$string['dodeleteaclgroup'] = 'Delete access to videos from this course';
$string['dodeleteevent'] = 'Delete video permanently';
$string['dodeletedraft'] = 'Delete video before transfer to Opencast';
$string['downloadvideo'] = 'Download video';
$string['download_settingheader'] = 'Download videos';
$string['download_setting'] = 'Download channel';
$string['download_settingdesc'] = 'Opencast publication channel from which the videos are served when downloading them.';
$string['duplicateworkflow'] = 'Workflow for duplicating events';
$string['duplicateworkflowdesc'] = 'This workflow is needed for importing opencast events from one course into another. If not set, it is not possible to import Opencast events.';

$string['editseries'] = 'Edit series';
$string['empty_catalogname'] = 'This field must not be empty';
$string['emptyvideouploaderror'] = 'You must either upload a presenter video or a presentation video file.';
$string['expirydate'] = 'Expiry Date';
$string['exists_catalogname'] = 'The field is already existed';
$string['enablechunkupload'] = 'Enable Chunkupload';
$string['enablechunkupload_desc'] = 'If Chunkupload is enabled it will be possible to upload videos using the chunkupload plugin';
$string['enableopencaststudiolink'] = 'Show the link to opencast studio';
$string['enableopencaststudiolink_desc'] = 'This option renders a button to opencast studio in the block content and the block overview.
Opencast studio has to run on your opencast admin node and the following lti settings have to be configured as well.';
$string['opencaststudiobaseurl'] = 'Opencast Studio Base URL';
$string['opencaststudiobaseurl_desc'] = 'The base URL to be used to call Opencast studio. The base URL of the opencast instance is used if empty.';
$string['error_eventid_taskdata_missing'] = 'The task data contains no event id.
    Opencast duplicate event task ({$a->taskid}) for course {$a->coursefullname} (ID: {$a->courseid}).';
$string['error_seriesid_missing_course'] = 'The course {$a->coursefullname} (ID: {$a->courseid}) has no course series. The event ({$a->eventid}) could not be restored.';
$string['error_seriesid_not_matching'] = 'The course {$a->coursefullname} (ID: {$a->courseid}) has a course series, that does not match the seriesid of the task.
    The event ({$a->eventid}) could not be restored.';
$string['error_seriesid_missing_opencast'] = 'The series of course {$a->coursefullname} (ID: {$a->courseid}) can not be found in the opencast system.
    The event ({$a->eventid}) could not be restored.';
$string['series_not_found'] = 'The series {$a} could not be found in the opencast system.';
$string['error_seriesid_taskdata_missing'] = 'The task data contains no series id.
    Opencast duplicate event task ({$a->taskid}) for course {$a->coursefullname} (ID: {$a->courseid}).';
$string['error_workflow_setup_missing'] = 'The plugin block_opencast is not properly configurated. The duplication workflow is missing!';
$string['error_workflow_not_exists'] = 'The workflow {$a->duplicateworkflow} can not be found in the opencast system.
    The event ({$a->eventid}) could not be restored for course {$a->coursefullname} (ID: {$a->courseid}).';
$string['error_workflow_not_started'] = 'The workflow to copy the video ({$a->eventid}) assigned to course {$a->coursefullname} (ID: {$a->courseid}) could not be started.';
$string['erroremailsubj'] = 'Error while executing opencast process duplicate task';
$string['erroremailbody'] = '{$a->errorstr} Details: {$a->message}.';
$string['errorduplicatetaskretry'] = 'An error occured by executing a task for duplication of an event: {$a} Will try to start the workflow again by the next cron job.';
$string['errorduplicatetaskterminate'] = 'An error occured by executing a task for duplication of an event: {$a}
    After trying serveral time the task will be terminated now.';
$string['errorrestoremissingevents_subj'] = 'Opencast error during restore process';
$string['errorrestoremissingevents_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    The video(s) with the following identifier(s) could not be found in opencast system. This video(s) will not be restored:';
$string['errorrestoremissingseries_subj'] = 'Opencast error during restore process';
$string['errorrestoremissingseries_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    No opencast series could be created. Therefore, the following eventIDs could not be duplicated:';
$string['errorrestoremissingimportmode_subj'] = 'Opencast error during restore process';
$string['errorrestoremissingimportmode_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    Import mode could not be identified, therefore restore task has failed.';
$string['errorrestoremissingsourcecourseid_subj'] = 'Opencast error during restore process';
$string['errorrestoremissingsourcecourseid_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    Source course id could not be found.';
$string['errorrestoremissingseriesid_subj'] = 'Opencast error during restore process';
$string['errorrestoremissingseriesid_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    Series id could not be found.';
$string['errorrestorefailedeventsaclchange_subj'] = 'Opencast error during restore process';
$string['errorrestorefailedeventsaclchange_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    The visibilty of the video(s) with the following identifier(s) could not be changed. This video(s) will not be accessible in the new course:';
$string['errorrestorefailedseriesaclchange_subj'] = 'Opencast error during restore process';
$string['errorrestorefailedseriesaclchange_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    The series ACL could not be changed (series: {$a->seriesid}).';
$string['errorrestorefailedseriesmapping_subj'] = 'Opencast error during restore process';
$string['errorrestorefailedseriesmapping_body'] = 'There was a problem in the restore process of the course {$a->coursefullname} (ID: {$a->courseid}).
    Mapping the series {$a->seriesid} to the new course failed.';
$string['eventdeleted'] = 'The video has been deleted.';
$string['eventdeletedfailed'] = 'Failed to delete the event';
$string['eventdeletionstarted'] = 'The video will be deleted shortly.';
$string['eventuploadsucceeded'] = 'Upload succeeded';
$string['eventuploadfailed'] = 'Upload failed';
$string['errorgetblockvideos'] = 'List cannot be loaded (Error: {$a})';

$string['failedtogetvideo'] = 'Cannot get event data from Opencast.';
$string['failedtransferattempts'] = 'Failed transfer attempts: {$a}';
$string['filetypes'] = 'Accepted file types';
$string['form_seriesid'] = 'Series ID';
$string['form_seriestitle'] = 'Series title';

$string['general_settings'] = 'General settings';
$string['gotooverview'] = 'Go to overview...';
$string['groupcreation'] = 'Create a group';
$string['groupcreationdesc'] = 'If checked, a group is created during the upload.';
$string['groupname'] = 'Group name';
$string['groupnamedesc'] = 'Group to which the video is added. Important: The group name length is restricted to 128 Bytes. You can use the placeholders [COURSEID] and [COURSENAME] which are automatically replaced.';
$string['groupseries_header'] = 'Group and Series';
$string['group_name_empty'] = 'The group name must not be empty if a group should be created.';

$string['heading_name'] = 'Field Name';
$string['heading_datatype'] = 'Field Type';
$string['heading_description'] = 'Field Description';
$string['heading_lti'] = 'Setting for LTI Configuration';$string['heading_position'] = 'Position';
$string['heading_required'] = 'Required';
$string['heading_readonly'] = 'Read Only';
$string['heading_params'] = 'Parameters (JSON)';
$string['heading_role'] = 'Role';
$string['heading_actions'] = 'Actions';
$string['heading_delete'] = 'Delete';
$string['heading_permanent'] = 'Permanent';
$string['haction'] = 'Action';
$string['hstart_date'] = 'Start Date';
$string['hend_date'] = 'End Date';
$string['htitle'] = 'Title';
$string['hlocation'] = 'Location';
$string['hworkflow_state'] = 'Status';
$string['hpublished'] = 'Published';
$string['hvisibility'] = 'Visibility';
$string['hprovide'] = 'Provide';
$string['hprovidelti'] = 'Provide (LTI)';

$string['identifier'] = 'Identifier';
$string['importseries'] = 'Import series';
$string['importfailed'] = 'The series could not be imported.';
$string['importmode'] = 'Import mode';
$string['importmodedesc'] = 'In order to define an approach to import videos into a course, a mode should be seleted. The default mode is Duplicating Events in which a new series would be created and events will be avaible in the series by using a dupliaction workflow. <br /> ACL Change approach on the other hand will use the same seriesid among courses but series and events\' ACLs are changes to grant access from the course which imports the videos.';
$string['importvideos_settingmodeduplication'] = 'Duplicating Events';
$string['importvideos_settingmodeacl'] = 'ACL Change';
$string['importvideos_errornotenabledorworking'] = 'The import videos feature is not enabled or not working';
$string['importvideos_importbuttontitle'] = 'Import videos';
$string['importvideos_importheading'] = 'Import videos from another course';
$string['importvideos_importjobcreated'] = 'The import of the selected videos into this course was scheduled. This process will happen in the background. You do not need to wait on this page. As soon as the process is started, the videos will appear in the <em>Videos available in this course</em> section';
$string['importvideos_importjobaclchangedone'] = 'The import of the selected series with its videos into this course was successful. You can now access the videos from the list.';
$string['importvideos_importjobaclchangeseriesfailed'] = 'Import failed, because the ACL change for selected series has failed.';
$string['importvideos_importjobaclchangeseriesmappingfailed'] = 'Import failed, becasue mapping the series to the course has failed.';
$string['importvideos_importjobaclchangealleventsfailed'] = 'Import has partially failed, because ACL change for all videos has failed.<br />However, the series is now mapped to this course and its ACL has been changed.';
$string['importvideos_importjobaclchangeeventsfailed'] = 'Import has partially failed, because ACL change for some of the videos has failed.<br />However, the series is now mapped to this course and its ACL has been changed.<br />The ACL of following eventIDs could not the changed:';
$string['importvideos_importjobcreationfailed'] = 'At least one of the videos could not be imported to this course. Please try again or contact your Moodle administrator.';
$string['importvideos_importepisodecleanupfailed'] = 'At least one of the existing Opencast episode modules within this course could not be cleaned up. Please try again or contact your Moodle administrator.';
$string['importvideos_importseriescleanupfailed'] = 'At least one of the existing Opencast series modules within this course could not be cleaned up. Please try again or contact your Moodle administrator.';
$string['importvideos_processingexplanation'] = 'These video files will be duplicated in Opencast and then made available in this course.';
$string['importvideos_sectionexplanation'] = 'In this section, you can import existing video files from other Moodle courses to this Moodle course.';
$string['importvideos_aclprocessingexplanation'] = 'The seriesid of the selected course will be used in this course and the series\' ACL as well as ACLs of its videos will be changed accordingly.';
$string['importvideos_settings'] = 'Import videos features';
$string['importvideos_settingcoreenabled'] = 'Allow video import within Moodle core course import wizard';
$string['importvideos_settingcoreenabled_desc'] = 'If enabled, teachers can import existing video files from other Moodle courses to their Moodle course by using the Moodle core course import wizard. Within the wizard, there is an additional option shown to import the videos which have been uploaded within the Opencast block in the other course. Using this feature, the teacher can import all videos from another course as bulk, but he cannot select individual videos.<br />When Dupliaction Events is selected as the Import mode then the videos are duplicated in Opencast by an ad-hoc task in Moodle and will show up in the course\'s video list with a short delay.<br />In case of ACL Change as Import mode, the seriesid of the course being imported is used but the ACL of the series and its videos will be changed in order to grant access within the new course.';
$string['importvideos_settingenabled'] = 'Allow video import';
$string['importvideos_settingenabled_desc'] = 'If enabled, teachers can import existing video files from other Moodle courses to their Moodle course. You have to further select an import mode between Duplicating Events or ACL Change approach. In both modes, you need to enable one or both of the two following settings to manually import videos or to import videos within the Moodle core course import wizard. If you don\'t enable any of these, this setting will have no effect.<br />In Duplicating Events mode, you need to further set a duplication workflow.';
$string['importvideos_settingheader'] = 'Import videos from other courses';
$string['importvideos_settinghandleepisodeenabled'] = 'Handle Opencast episode modules during manual video import';
$string['importvideos_settinghandleepisodeenabled_desc'] = 'If enabled, teachers can handle Opencast episode modules which were created with the "Add LTI episode module" feature and which relate to the videos which are imported. This especially cleans up modules which have been imported from one course to another course but which still link to videos in the old course. The episode modules are handled by an ad-hoc task in Moodle and will be fixed in the course with a short delay.<br />Please note that this mechanism relies on the fact that the Opencast duplication workflow sets a workflow instance configuration field \'duplicate_media_package_1_id\' which contains the episode ID of the duplicated episode. If the workflow does not set this configuration field, this feature will always fail.';
$string['importvideos_settinghandleseriesenabled'] = 'Handle Opencast series modules during manual video import';
$string['importvideos_settinghandleseriesenabled_desc'] = 'If enabled, teachers can handle Opencast series modules which were created with the "Add LTI series module" feature and which relate to the videos which are imported. This especially cleans up modules which have been imported from one course to another course but which still link to the course series of the old course. The series module is instantly handled after the import wizard is finished.';
$string['importvideos_settingmanualenabled'] = 'Allow manual video import';
$string['importvideos_settingmanualenabled_desc'] = 'If enabled, teachers can import existing video files from other Moodle courses to their Moodle course by using a dedicated Opencast video import wizard. This feature is offered on the Opencast block overview page.<br /> In Duplicating Events import mode, the teacher can import all or a subset of videos from another course as bulk. The videos which are selected within the import wizard are duplicated in Opencast by an ad-hoc task in Moodle and will show up in the course\'s video list with a short delay.<br />In ACL Change import mode, the seriesid of the the target course will be used in the new course. Additionally, the ACLs of the series itself and all of its videos will be changed in order to grant access to them within the new course.<br />Unlike in Duplicating Events Mode, there is no manual selection of videos available within the provided wizard.<br />Please note that there are corresponding capabilities block/opencast:manualimporttarget and block/opencast:manualimportsource for this feature which control who can import which courses to which courses. By default, managers and editing teachers have this capability and can use the feature as soon as it is enabled here.';
$string['importvideos_progressbarstep'] = 'Step {$a->current} of {$a->last}';
$string['importvideos_wizardstep1heading'] = 'Select source course';
$string['importvideos_wizardstep1intro'] = 'Select the source course from where the videos should be imported from the following list of courses.<br />You can pick from all courses which you are allowed to import videos from.';
$string['importvideos_wizardstep1sourcecourse'] = 'Import videos from this course';
$string['importvideos_wizardstep1series'] = 'Default series of this course';
$string['importvideos_wizardstep1seriesnotfound'] = 'The source course has no valid series id, please make sure the course you have selected has a default series.';
$string['importvideos_wizardstep1coursevideos'] = 'Videos to be imported';
$string['importvideos_wizard_seriesimported'] = 'Series to be imported';
$string['importvideos_wizardstep1sourcecoursenone'] = 'There isn\'t any other course than this course which you are allowed to import videos from.';
$string['importvideos_wizardstep2coursevideos'] = 'Import these videos';
$string['importvideos_wizardstep2coursevideosnone'] = 'There isn\'t any video in the selected course.';
$string['importvideos_wizardstep2coursevideosnoneselected'] = 'You have to check at least one video to be imported.';
$string['importvideos_wizardstep2heading'] = 'Select videos';
$string['importvideos_wizardstep2intro'] = 'Select the videos which you want to import from the source course.<br />You can pick from all videos which have been processed completely in the default series of the source course.';
$string['importvideos_wizardstep2aclheading'] = 'Select series';
$string['importvideos_wizardstep2aclintro'] = 'Select the series which you want to import from the source course.<br/>All videos from this series are imported.';
$string['importvideos_wizard_availableseries'] = 'Available series in {$a}';
$string['importvideos_wizardstep3aclheading'] = 'Summary';
$string['importvideos_wizardstep3aclintro'] = 'Please verify that the summary of the video import matches your expectations before you run the import.';
$string['importvideos_wizardstep3heading'] = 'Handle Opencast modules in this course';
$string['importvideos_wizardstep3intro'] = 'There are existing Opencast modules in this course which relate to the videos which you are going to import. These modules will be handled after the video import.<br /><strong>If you are unsure if you need this handling, please accept the given defaults.</strong>';
$string['importvideos_wizardstep3skipintro'] = 'There aren\'t any existing Opencast modules in this course which relate to the videos which you are going to import.<br />Nothing needs to be handled, please continue to the next step.';
$string['importvideos_wizardstep3episodemodulesubheading'] = 'Clean up existing Opencast episode module(s)';
$string['importvideos_wizardstep3episodemoduleexplanation'] = 'There is at least one Opencast episode module in this course which points to a video in the course from where you are going to import the videos now. After the import, this will be cleaned up so that it points to imported video within this course.';
$string['importvideos_wizardstep3episodemodulelabel'] = 'Yes, clean up the Opencast episode module(s) related to this import';
$string['importvideos_wizardstep3seriesmodulesubheading'] = 'Clean up existing Opencast series module(s)';
$string['importvideos_wizardstep3seriesmoduleexplanation'] = 'There is at least one Opencast series module in this course which points to the course from where you are going to import the videos now. After the import, this will be cleaned up so that only the Opencast series module which shows all (pre-existing and to be imported) videos of this course remains.';
$string['importvideos_wizardstep3seriesmodulelabel'] = 'Yes, clean up the Opencast series modules related to this import';
$string['importvideos_wizardstep4heading'] = 'Summary';
$string['importvideos_wizardstep4intro'] = 'Please verify that the summary of the video import matches your expectations before you run the import.<br/>The videos are imported into the default series.';
$string['importvideos_wizardstep4coursevideosnone'] = 'There wasn\'t any video selected for import.';
$string['importvideos_wizardstep4sourcecoursenone'] = 'There wasn\'t any source course selected for import.';
$string['importvideos_wizardstepbuttontitlecontinue'] = 'Continue';
$string['importvideos_wizardstepbuttontitlerunimport'] = 'Import videos and return to overview';
$string['ingestupload'] = 'Ingest upload';
$string['ingestuploaddesc'] = 'Use the Opencast ingest service for uploading videos.';
$string['invalidacldata'] = 'Invalid acl data';

$string['language'] = 'Language';
$string['limituploadjobs'] = 'Limit upload job by cron';
$string['limituploadjobsdesc'] = 'Limit the count of uploadjobs done by one cronjob. The cronjob can be scheduled here: {$a}';
$string['limitvideos'] = 'Number of videos';
$string['limitvideosdesc'] = 'Maximum number of videos to display in block';
$string['loading'] = 'Loading...';
$string['ltimodule_settings'] = 'LTI module features';
$string['lticonsumerkey'] = 'Consumer key';
$string['lticonsumerkey_desc'] = 'LTI Consumer key for the opencast studio integration. If the integration is enabled but no key is set, the link without LTI authentication will be shown.';
$string['lticonsumersecret'] = 'Consumer secret';
$string['lticonsumersecret_desc'] = 'LTI Consumer secret for the opencast studio integration.';
$string['license'] = 'License';
$string['location'] = 'Location';

$string['manageseriesforcourse'] = 'Manage series';
$string['maxseries'] = 'Maximum number of series';
$string['maxseriesdesc'] = 'Specifies how many series can be assigned to a course. Teachers won\'t be able to add/import more series if the maximum number is reached.';
$string['maxseriesreached'] = 'You cannot add another series to this course because the course contains already the maximum number of series.';
$string['maxseriesreachedimport'] = 'Currently, you cannot import another series because the course contains already the maximum number of series.';
$string['messageprovider:error'] = 'Processing error notification';
$string['messageprovider:reportproblem_confirmation'] = 'Copy of reported problems for Opencast videos';
$string['metadata'] = 'Event Metadata';
$string['metadatadesc'] = 'Here you can define which metadata fields can or have to be set when uploading videos to Opencast. You can drag-and-drop rows to reorder them defining the position of fields in the form.';
$string['metadataexplanation'] = 'When uploading existing video files to Opencast, you can set several metadata fields. These will be stored together with the video.';
$string['metadataseries'] = 'Series Metadata';
$string['metadataseriesdesc'] = 'Here you can define which metadata fields can or have to be set for creating Opencast series. You can drag-and-drop rows to reorder them defining the position of fields in the form.';
$string['metadataseriesupdatefailed'] = 'Updating the series metadata failed.';
$string['mediatype'] = 'Media Source';
$string['metadata_autocomplete_placeholder'] = 'Enter {$a}';
$string['metadata_autocomplete_noselectionstring'] = 'No {$a} provided!';
$string['messageprovider:opencasteventstatus_notification'] = 'Opencast event status notification';
$string['missingevent'] = 'Creation of event failed';
$string['missinggroup'] = 'Missing group in opencast';
$string['missingseries'] = 'Missing series in opencast';
$string['missingseriesassignment'] = 'Missing series assignment';
$string['morevideos'] = 'More videos...';
$string['mstatereadytoupload'] = 'Ready for transfer';
$string['mstatecreatinggroup'] = 'Creating Opencast Group...';
$string['mstatecreatingseries'] = 'Creating Opencast Series...';
$string['mstatecreatingevent'] = 'Uploading...';
$string['mstateuploaded'] = 'Processing post-upload tasks...';
$string['mstatetransferred'] = 'Transferred';
$string['mstateunknown'] = 'State unknown';

$string['noconnectedseries'] = 'No series is defined yet.';
$string['no_ingest_services'] = 'No available ingest nodes found.';
$string['noseriesid'] = 'Series ID is not defined yet.';
$string['nothingtodisplay'] = 'In this section, you see the videos which are uploaded to this course.<br />Currently, no videos have been uploaded to this course yet.';
$string['notpublished'] = 'Not published';
$string['notifications_settings_header'] = 'Notifications';
$string['notificationeventstatus'] = 'Allow event process status notification';
$string['notificationeventstatus_desc'] = 'This option allows the system to send notification about the status of the uploaded video from Opencast during the process to the users. This option only includes uploader of the video into the user list. To Notify all teachers of the course, the following setting should be enabled.<br />This feature is done through a scheduled task that is recommended to run every minute.';
$string['notificationeventstatusteachers'] = 'Notify all course teachers about the event process status';
$string['notificationeventstatusteachers_desc'] = 'With this option, apart from the uploader of the video, all the teachers of the course where the video is uploaded from get notified about the Opencast processing status.';
$string['notificationeventstatusdeletion'] = 'Cleanup notification jobs after (Days)';
$string['notificationeventstatusdeletion_desc'] = 'This setting sets a deadline in day for the jobs that are not yet completed. After the deadline has reached, the job will be deleted despite its process state. <br />This features helps to remove the pending notification jobs from the list and it is done through a scheduled task that is recommended to run once a day.<br />NOTE: (zero) "0" value disables the setting.';
$string['notificationeventstatus_subj'] = 'Opencast Event Status Notification';
$string['notificationeventstatus_body'] = 'The process status of the uploaded video: {$a->videotitle} (ID: {$a->videoidentifier}) in the course: {$a->coursefullname} (ID: {$a->courseid}) has been changed to: {$a->statusmessage}';
$string['novideosavailable'] = 'No videos available';

$string['ocstatefailed'] = 'Failed';
$string['ocstateprocessing'] = 'Processing';
$string['ocstatesucceeded'] = 'Succeeded';
$string['ocstatecapturing'] = 'Capturing';
$string['ocstateneedscutting'] = 'Needs cutting';
$string['offerchunkuploadalternative'] = 'Offer filepicker as alternative';
$string['offerchunkuploadalternative_desc'] = 'If this option is enabled, a checkbox labeled as \'{$a}\' will be shown below the chunk upload widget. As soon as this checkbox is checked, the chunk upload widget is hidden and the Moodle default file picker is shown, giving access to all Moodle repositories.';
$string['opencast:addinstance'] = 'Add a new opencast upload block';
$string['opencast:addlti'] = 'Add Opencast LTI series module to course';
$string['opencast:addltiepisode'] = 'Add Opencast LTI episode module to course';
$string['opencast:addactivity'] = 'Add Opencast Video Provider activity to course';
$string['opencast:addactivityepisode'] = 'Add Opencast Video Provider activity for an episode to course';
$string['opencast:addvideo'] = 'Add a new video to opencast upload block';
$string['opencast:createseriesforcourse'] = 'Create a new series in opencast for a moodle course';
$string['opencast:deleteevent'] = 'Finally delete a video (event) in opencast';
$string['opencast:defineseriesforcourse'] = 'Link an existing opencast series to a moodle course';
$string['opencast:importseriesintocourse'] = 'Import an existing opencast series to a moodle course';
$string['opencast:manageseriesforcourse'] = 'Manage the opencast series of a moodle course';
$string['opencast:downloadvideo'] = 'Download processed videos';
$string['opencast:startworkflow'] = 'Manually start workflows for videos';
$string['opencast:manualimportsource'] = 'Manually import videos from this course';
$string['opencast:manualimporttarget'] = 'Manually import videos from other courses';
$string['opencast:myaddinstance'] = 'Add a new opencast upload block to Dashboard';
$string['opencast:unassignevent'] = 'Unassign a video from the course, where the video was uploaded.';
$string['opencast:viewunpublishedvideos'] = 'View all the videos from opencast server, even when they are not published';
$string['opencaststudiointegration'] = 'Opencast studio integration';
$string['overview'] = 'Overview';

$string['planned'] = 'Planned';
$string['legendplanneddesc'] = 'The video has been scheduled for processing.';
$string['legenddeletingdesc'] = 'The video is being deleted, and it will be removed shortly.';
$string['pluginname'] = 'Opencast Videos';
$string['publisher'] = 'Publisher';
$string['presenterfile'] = 'Presenter file';
$string['presentationfile'] = 'Presentation file';
$string['presenter'] = 'Presenter video';
$string['presenterdesc'] = 'Use the presenter video if you have a video file of a person speaking to an audience or a motion picture.';
$string['presentation'] = 'Presentation video';
$string['presentationdesc'] = 'Use the presentation video if you have a video file of a slide presentation recording or a screencast.';
$string['privacy:metadata:block_opencast_uploadjob'] = 'Information about video uploads.';
$string['privacy:metadata:block_opencast_uploadjob:fileid'] = 'ID of the file/video which is uploaded';
$string['privacy:metadata:block_opencast_uploadjob:opencasteventid'] = 'ID of the opencast event that was created during upload';
$string['privacy:metadata:block_opencast_uploadjob:userid'] = 'ID of the user who uploaded the video';
$string['privacy:metadata:block_opencast_uploadjob:status'] = 'Status of upload process';
$string['privacy:metadata:block_opencast_uploadjob:courseid'] = 'ID of the course where the video is uploaded';
$string['privacy:metadata:block_opencast_uploadjob:timecreated'] = 'The date the upload job was created.';
$string['privacy:metadata:block_opencast_uploadjob:timemodified'] = 'The date the upload job was last modified.';
$string['privacy:metadata:core_files'] = 'The opencast block stores files (videos) which have been uploaded by the user.';
$string['privacy:metadata:opencast'] = 'The block interacts with an opencast instance and thus data needs to be exchanged.';
$string['privacy:metadata:opencast:file'] = 'The file which is selected is uploaded to opencast.';
$string['processepisodecleanup'] = 'Process Opencast Video Provider/LTI episode cleanup after course imports';
$string['processupload'] = 'Process upload';
$string['processdelete'] = 'Process delete jobs for Opencast';
$string['processnotification'] = 'Process event status notification jobs after upload';
$string['processdeletenotification'] = 'Process delete notification jobs';
$string['publishtoengage'] = 'Publish to Engage';
$string['publishtoengagedesc'] = 'Select this option to publish the video after upload to engage player. Setup workflow must support this.';

$string['reuseexistingupload'] = 'Reuse existing uploads';
$string['reuseexistinguploaddesc'] = 'If activated, multiple videos with the same content hash are uploaded to opencast only once.
This saves storage and processing power, but it might cause problems, when you use specific access policies based on opencast series.';
$string['rolename'] = 'Role name';
$string['reportproblem_modal_title'] = 'Report a problem';
$string['reportproblem_modal_body'] = 'If you there is a problem with a video, you can report it to the support team.';
$string['reportproblem_modal_placeholder'] = 'Please explain the problem.';
$string['reportproblem_modal_submit'] = 'Report problem';
$string['reportproblem_modal_required'] = 'Please enter a message.';
$string['reportproblem_success'] = 'The email was successfully sent to the support team.';
$string['reportproblem_failure'] = 'The email could not be sent. Please contact the support team manually.';
$string['reportproblem_notification'] = '#### This is a copy of the email sent to the support team. ####<br>';
$string['reportproblem_email'] = 'This is an automatic notification of the Moodle Opencast plugin.<br>Somebody reported a problem with a video.<br><br><b>User: </b>{$a->username}<br><b>User email: </b>{$a->useremail}<br><b>Moodle course: </b><a href="{$a->courselink}">{$a->course}</a><br><b>Opencast series: </b>{$a->series} (Id: {$a->seriesid})<br><b>Opencast event id: </b>{$a->event} (Id: {$a->eventid})<br><b>Message:</b><br><hr>{$a->message}<hr>';
$string['reportproblem_subject'] = 'Moodle Opencast plugin: Problem reported';
$string['restoreopencastvideos'] = 'Restore videos from Opencast instance {$a}';
$string['recordvideo'] = 'Record video';
$string['rightsHolder'] = 'Rights';

$string['series'] = 'Series';
$string['series_already_exists'] = 'This course is already assigned to a series.';
$string['seriescreated'] = 'Series was created.';
$string['seriesnotcreated'] = 'Series could not be created.';
$string['seriesidsaved'] = 'The series ID was saved.';
$string['seriesidunset'] = 'The series ID was removed.';
$string['seriesidnotvalid'] = 'The series {$a} does not exist.';
$string['series_does_not_exist_admin'] = 'The series with the identifier \'{$a}\' could not be retrieved from Opencast.';
$string['series_does_not_exist'] = 'The series assigned to this course is not valid. Please contact your Administrator!';
$string['seriesname'] = 'Series name';
$string['seriesnamedesc'] = 'Series to which the video is added. You can use placeholders which are automatically replaced (<a target="_blank" href="https://moodle.docs.opencast.org/#block/general_settings/#placeholders">list of placeholders</a>).';
$string['series_name_empty'] = 'Series name must not be empty.';
$string['series_used'] = 'Used in {$a} courses';
$string['seriesonedefault'] = 'There must be exactly one default series.';
$string['setdefaultseries_heading'] = 'Set default series';
$string['setdefaultseries'] = 'Do you really want to use this series as new default series?';
$string['setdefaultseriesfailed'] = 'Changing the default series failed. Please try again later or contact an administrator.';
$string['settings'] = 'Opencast Videos';
$string['setting_permanent'] = 'Is permanent';
$string['source'] = 'Source';
$string['startDate'] = 'Date';
$string['startTime'] = 'Time';
$string['subjects'] = 'Subjects';
$string['space_catalogname'] = 'This field must not contain space';
$string['startworkflow'] = 'Start workflow';
$string['startworkflow_modal_body'] = 'Choose the workflow you want to start.';
$string['submit'] = 'Save changes';
$string['shared_settings'] = 'Shared settings';
$string['support_setting'] = 'Support email';
$string['support_settingdesc'] = 'Email address to which reports are sent if users report problems with videos.';
$string['support_setting_notset'] = 'Support email is not set. Please contact the administrator and resubmit the report.';

$string['termsofuse'] = 'Terms of use';
$string['termsofuse_accept'] = 'I have read and agree to the {$a}.';
$string['termsofuse_accept_toggle'] = 'terms of use';
$string['termsofuse_desc'] = 'If you enter some text, a checkbox with the terms of use will appear on the "Video upload" page.
The users must accept the entered terms of use before they can upload the video.';
$string['title'] = 'Title';
$string['tool_requirement_not_fulfilled'] = 'The required version of tool_opencast is not installed.';
$string['type'] = 'Media Type';

$string['unexpected_api_response'] = 'Unexpected API response.';
$string['uploadfileextensions'] = 'Allowed file extensions';
$string['uploadfileextensionsdesc'] = 'Comma separated list of allowed video file extensions (extensions must exist in Moodle\'s <a href="{$a}">File types</a> list). If left blank all extensions with type group \'video\' are allowed (again see <a href="{$a}">File types</a>).';
$string['uploadingeventfailed'] = 'Creating of event failed';
$string['uploadjobssaved'] = 'Video upload successful.<br />The video is scheduled to be transferred to Opencast now. You do not need to wait on this page for this transfer to finish.';
$string['uploadqueuetoopencast'] = 'Videos scheduled to be transferred to Opencast';
$string['uploadqueuetoopencastexplanation'] = 'In this section, you see the videos which have been uploaded to this Moodle course by you or some other user. These videos are scheduled to be transferred to Opencast now.<br />The transfer is happening automatically on the Moodle server in the background. You do not need to wait on this page for this transfer to finish.';
$string['uploadrecordvideos'] = 'Upload or record videos';
$string['uploadrecordvideosexplanation'] = 'In this section, you can upload existing video files to Moodle. Additionally, you can record video files directly with Opencast Studio.';
$string['uploadvideos'] = 'Upload videos';
$string['uploadvideosexplanation'] = 'In this section, you can upload existing video files to Moodle.';
$string['uploadprocessingexplanation'] = 'These video files will be processed in Opencast and then made available in this course.';
$string['uploadworkflow'] = 'Workflow to start after upload';
$string['uploadworkflowdesc'] = 'Setup the unique shortname of the workflow, that should be started after succesfully uploading a video file to opencast.
    If left blank the standard workflow (ng-schedule-and-upload) will be used. Ask for additional workflows that may have been created by the opencast administrator.';
$string['uploadsettings'] = 'Settings for the chunkuploader';
$string['uploadfilelimit'] = 'Video size limit';
$string['uploadfilelimitdesc'] = 'Limit the file size of uploaded videos through the chunkupload.';
$string['updatemetadatasaved'] = 'Metadata is saved.';
$string['updatemetadatafailed'] = 'The metadata could not be saved.';
$string['updatemetadata'] = 'Update metadata for this event';
$string['updatemetadata_short'] = 'Update metadata';
$string['upload'] = 'File Upload';
$string['uploadexplanation'] = 'You have the option to upload a presenter video file and / or a presentation video file.<br />Most likely you will only upload one file, but Opencast is also capable of dealing with two videos at once which will be combined in a media package.';
$string['usedefaultfilepicker'] = 'Use Moodle default file picker to access all repositories';

$string['videosavailable'] = 'Videos available in this course';
$string['video_notallowed'] = 'The video is not part of the course series.';
$string['videonotfound'] = 'Video not found';
$string['videodraftnotfound'] = 'The video to be deleted before the transfer to Opencast was not found.';
$string['videodraftnotdeletable'] = 'The video cannot be deleted anymore before the transfer to Opencast as the processing status is already "{$a}"';
$string['videodraftdeletionsucceeded'] = 'The video is deleted successfully';
$string['video_retrieval_failed'] = 'Video could not be retrieved. Please check the connection to the Opencast server.';
$string['video_not_downloadable'] = 'This video was not published in the download channel.';
$string['videostoupload'] = 'Videos to upload to opencast';
$string['visibility'] = 'Visibility of the video';
$string['visibility_hide'] = 'Prevent any student from accessing the video';
$string['visibility_show'] = 'Allow all students of the course to access the video';
$string['visibility_group'] = 'Allow all students belonging to selected groups to access the video';

$string['workflow_settings_opencast'] = 'Workflow Settings';
$string['workflownotdefined'] = 'The workflow for updating metadata is not defined.';
$string['worklowisrunning'] = 'A workflow is running. You cannot change the visibility at the moment.';
$string['workflowrolesname'] = 'Workflow for updating metadata';
$string['workflowrolesdesc'] = 'This workflow is triggered when updating event metadata or deleting/adding nonpermanent ACL rules. If not set, it will not be possible to change the visibility of uploaded videos through the block.';
$string['workflow_started_success'] = 'Workflow successfully started.';
$string['workflow_started_failure'] = 'Starting workflow failed.';
$string['workflow_invalid'] = 'This workflow does not exist or is not enabled.';
$string['workflow_opencast_invalid'] = 'This workflow does not exist in Opencast or is restricted. Please contact the administrator.';
$string['workflowtag_setting'] = 'Workflow tag';
$string['workflowtag_settingdesc'] = 'Tag for Opencast workflows that can be configured by the admin and manually started by teachers.';
$string['workflow_not_existing'] = 'This workflow does not exist in Opencast.';
$string['wrongmimetypedetected'] = 'An invalid mimetype was used while uploading the video {$a->filename} from course {$a->coursename}.
    Only video files are allowed!';

// Opencast Editor Integration strings.
$string['videoeditor_short'] = 'Video Editor';
$string['videoeditorinvalidconfig'] = 'Currently, it is not possible to use Opencast Editor in order to edit the event.';
$string['opencasteditorintegration'] = 'Opencast Editor integration';
$string['enableopencasteditorlink'] = 'Show the link to opencast Editor in action menu';
$string['enableopencasteditorlink_desc'] = 'This option renders a button to opencast editor in the block content and the block overview.
 The following settings as well lti credentials have to be configured as well.';
$string['editorbaseurl'] = 'Opencast Editor Base URL';
$string['editorbaseurl_desc'] = 'The base URL to be used to call the Opencast Editor, the base url of the opencast instance is used if empty.';
$string['editorendpointurl'] = 'Opencast Editor Endpoint';
$string['editorendpointurl_desc'] = 'The editor endpoint to access the editor. The mediapackage ID will be added at the end of the url.';
$string['editorlticonsumerkey'] = 'Opencast Editor Consumer key';
$string['editorlticonsumerkey_desc'] = 'LTI Consumer key for the opencast editor integration.';
$string['editorlticonsumersecret'] = 'Opencast Editor Consumer secret';
$string['editorlticonsumersecret_desc'] = 'LTI Consumer secret for the opencast editor integration.';

// Strings fot new opencast studio settings.
$string['opencaststudionewtab'] = 'Redirect to Studio in a new tab';
$string['opencaststudionewtab_desc'] = 'When enabled, studio opens in a new tab.';
$string['enableopencaststudioreturnbtn'] = 'Show a redirect back button in Studio';
$string['enableopencaststudioreturnbtn_desc'] = 'When enabled, Studio then renders an additional button "Exit and go back" after up- or downloading the recording.';
$string['opencaststudioreturnurl'] = 'Custom Studio return endpoint URL';
$string['opencaststudioreturnurl_desc'] = 'When empty the return url redirects back to the same Moodle opencast block overview where the request comes from. A custom endpoint URL will then be passed to Studio as return url when configured, in this case, admin is able to use 2 placeholders including [OCINSTANCEID] and [COURSEID]. Please NOTE: the URL must be relative to wwwroot.';
$string['opencaststudioreturnbtnlabel'] = 'Label for Studio\'s return button';
$string['opencaststudioreturnbtnlabel_desc'] = 'This label works as a short description where the return link leads to. This label will be appended to the Studio return button text, when empty, moodle site name will be passed as label.';

// Strings for new visibility feature during initail upload
$string['visibilityheader'] = 'Event Visibility';
$string['visibilityheaderexplanation'] = 'You are able to set the initial visibility status of the video before upload, as well as scheduling a visibility change when it is configured to do so.';
$string['scheduledvisibilitytime'] = 'Change video visibility on';
$string['scheduledvisibilitytimehi'] = 'Scheduling date';
$string['scheduledvisibilitytimehi_help'] = 'This date must be set in the near future and as recommended at least 20 minutes after the current date time, however the faster your opencast server processes the video the nearer this date could be set.';
$string['scheduledvisibilitytime'] = 'Change video visibility on';
$string['scheduledvisibilitytimeerror'] = 'The scheduled date to change visibility must be set at least 20 minutes after the current data and time.';
$string['initialvisibilitystatus'] = 'Initial visibility of the video';
$string['scheduledvisibilitystatus'] = 'Change video visibility to';
$string['enableschedulingchangevisibility'] = 'Schedule a visibility change';
$string['enableschedulingchangevisibilitydesc'] = 'Set a date and a visibility status for the event in future, which will be performed using a scheduled task.';
$string['processvisibility'] = 'Process scheduled visibility change jobs';

// Deprecated since version 2021062300.
$string['video_already_uploaded'] = 'Video already uploaded';
