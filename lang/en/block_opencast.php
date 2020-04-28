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
$string['aclrolesadded'] = 'The change of visibility has been triggered to allow all students of the course to access the video: {$a->title}

Please refresh the site after some time to see the current visibility status.';
$string['aclroleschangeerror'] = 'Error during the change of visibility of the video: {$a->title}

Some changes might have not been saved.
If this occurs repeatedly, please contact your support team.';
$string['aclrolesdeleted'] = 'The change of visibility has been triggered to prevent all students of the course from accessing the video: {$a->title}

Please refresh the site after some time to see the current visibility status.';
$string['aclrolesaddedgroup'] = 'The change of visibility has been triggered to allow students of selected groups to access the video: {$a->title}

Please refresh the site after some time to see the current visibility status.';
$string['aclnothingtobesaved'] = 'No changes to the visibility have been made.';
$string['accesspolicies'] = 'Access Policies';
$string['aclrolesname'] = 'Roles';
$string['aclrolesnamedesc'] = 'You can use the placeholder [COURSEID] in the role name which is automatically replaced.';
$string['actions'] = 'Comma-separated list of actions';
$string['adhocfiledeletion'] = 'Delete videofile from moodle';
$string['adhocfiledeletiondesc'] = 'If activated the plugin tries to delete the videofile from moodle\'s filessystem right after it was uploaded to opencast server.
    Please note that the file will still remain in the file system, if it is used within other places in moodle.';
$string['addlti_modulecreated'] = 'The \'{$a}\' series module has been added to this course.';
$string['addlti_moduleexists'] = 'There is already an Opencast series module added to this course. There is no need to add another one.';
$string['addlti_modulenotcreated'] = 'The \'{$a}\' series module could not be created. Please try again or contact your Moodle administrator.';
$string['addlti_addbuttonexplanation'] = 'The videos which are added to and are available in this course are not provided to your students automatically.<br />Here, you can add an Opencast series module to your course which provides all available videos as an Opencast series list to your students.';
$string['addlti_addbuttontitle'] = 'Add Opencast series module to course';
$string['addlti_defaulttitle'] = 'Opencast videos';
$string['addlti_formltititle'] = 'Opencast series module title';
$string['addlti_header'] = 'Provide videos';
$string['addlti_noemptytitle'] = 'You have to set a title for the Opencast series module or to use the default title ("{$a}")';
$string['addlti_settingdefaulttitle'] = 'Default LTI module title';
$string['addlti_settingdefaulttitle_desc'] = 'The default title to be used when a new Opencast LTI module is added to a course.';
$string['addlti_settingenabled'] = 'Enable ”Add LTI module"';
$string['addlti_settingenabled_desc'] = 'If enabled, teachers can add an Opencast LTI module to a course. This LTI module will be pointing to the course\'s Opencast series.';
$string['addlti_settingheader'] = 'Add Opencast LTI module to courses';
$string['addlti_settingpreconfiguredtool'] = 'Preconfigured LTI tool';
$string['addlti_settingpreconfiguredtool_desc'] = 'The preconfigured LTI tool to be used when a new Opencast LTI module is added to a course.';
$string['addlti_settingpreconfiguredtool_notools'] = 'No preconfigured LTI tools to be used found. Please create an Opencast LTI tool first on the {$a} admin setting page.';
$string['addlti_viewbuttonexplanation'] = 'An Opencast series module to provide the videos available in this course has been added to the course before.';
$string['addlti_viewbuttontitle'] = 'View Opencast series module in course';
$string['addvideo'] = 'Add video';
$string['addrole'] = 'Add new role';
$string['adminchoice_noworkflow'] = "-- No workflow-- ";
$string['changevisibility_visible'] = 'The video is visible to all students of the course. Click to alter visibility.';
$string['changevisibility_mixed'] = 'The visibility of the video is in an invalid status. Click to choose the correct visibility.';
$string['changevisibility_hidden'] = 'The video is visible to no student. Click to alter visibility.';
$string['changevisibility_group'] = 'The video is visible to all student belonging to selected groups. Click to alter visibility.';
$string['changevisibility_header'] = 'Change visibility for {$a->title}';
$string['changevisibility'] = 'Alter visibility';
$string['allowunassign'] = 'Allow unassign from course';
$string['allowunassigndesc'] = 'Delete the assignment of a course series to control visibility in filepicker and course lists. This feature is only available,
    when it is possible to have events without series in opencast. Please ask the admistrator of the opencast system before activating this.';
$string['backupopencastvideos'] = 'Backup Opencast videos';
$string['backupsettings'] = 'Settings for backup and restore';
$string['blocksettings'] = 'Settings for a block instance';
$string['countfailed'] = 'Error';
$string['coursefullnameunknown'] = 'Unkown coursename';
$string['createdby'] = 'Created by';
$string['createseriesforcourse'] = 'Create new series';
$string['cronsettings'] = 'Settings for upload jobs';
$string['deleteaclgroup'] = 'Delete video from this list.';
$string['delete_confirm_role'] = 'Are you sure you want to delete this role?';
$string['delete_confirm_catalog'] = 'Are you sure you want to delete this catalog entry?';
$string['deleteevent'] = 'Delete a event in opencast';
$string['deleteeventdesc'] = 'You are about to delete this video permanently and irreversibly from opencast.
    All embedded links to it will become invalid. Please do not continue unless you are absolutely sure.';
$string['deletegroupacldesc'] = 'You are about to delete the access to this video from this course.
    If the access is deleted, the video is not displayed in filepicker and in the list of available videos. This does not affect videos, which are already embedded.
    The video will not be deleted in Opencast.';
$string['deleteworkflow'] = 'Workflow to start before event is be deleted';
$string['deleteworkflowdesc'] = 'Before deleting a video, a workflow can be defined, which is called to retract the event from all publication channels.';
$string['dodeleteaclgroup'] = 'Delete access to videos from this course';
$string['duplicateworkflow'] = 'Workflow for duplicating events';
$string['duplicateworkflowdesc'] = 'This workflow is needed for restoring opencast events from one course into another.
    If not set, it is not possible to restore Opencast events. A block instance might be restore, but without a series and without events.';
$string['editseriesforcourse'] = 'Edit series mapping';
$string['form_seriesid'] = 'Series ID';
$string['form_seriestitle'] = 'Series title';
$string['dodeleteevent'] = 'Delete video permanently';
$string['deleting'] = 'Going to be deleted';
$string['error_eventid_taskdata_missing'] = 'The task data contains no event id.
    Opencast duplicate event task ({$a->taskid}) for course {$a->coursefullname} (ID: {$a->courseid}).';
$string['error_seriesid_missing_course'] = 'The course {$a->coursefullname} (ID: {$a->courseid}) has no course series. The event ({$a->eventid}) could not be restored.';
$string['error_seriesid_not_matching'] = 'The course {$a->coursefullname} (ID: {$a->courseid}) has a course series, that does not match the seriesid of the task.
    The event ({$a->eventid}) could not be restored.';
$string['error_seriesid_missing_opencast'] = 'The series of course {$a->coursefullname} (ID: {$a->courseid}) can not be found in the opencast system.
    The event ({$a->eventid}) could not be restored.';
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
$string['eventdeleted'] = 'The video has been deleted.';
$string['eventdeletedfailed'] = 'Failed to delete the event';
$string['eventdeletionstarted'] = 'The video will be deleted shortly.';
$string['eventuploadsucceeded'] = 'Upload succeeded';
$string['eventuploadfailed'] = 'Upload failed';
$string['errorgetblockvideos'] = 'List cannot be loaded (Error: {$a})';
$string['gotooverview'] = 'Go to overview...';
$string['groupcreation'] = 'Create a group';
$string['groupcreationdesc'] = 'If checked, a group is created during the upload.';
$string['groupname'] = 'Group name';
$string['groupnamedesc'] = 'Group to which the video is added. Important: The group name length is restricted to 128 Bytes. You can use the placeholders [COURSEID] and [COURSENAME] which are automatically replaced.';
$string['groupseries_header'] = 'Group and Series';
$string['group_name_empty'] = 'The group name must not be empty if a group should be created.';
$string['heading_role'] = 'Role';
$string['heading_actions'] = 'Actions';
$string['heading_delete'] = 'Delete';
$string['heading_permanent'] = 'Permanent';
$string['hstart_date'] = 'Start Date';
$string['hend_date'] = 'End Date';
$string['htitle'] = 'Name';
$string['hlocation'] = 'Location';
$string['hworkflow_state'] = 'Status';
$string['hpublished'] = 'Published';
$string['hvisibility'] = 'Visibility';
$string['invalidacldata'] = 'Invalid acl data';
$string['limituploadjobs'] = 'Limit upload job by cron';
$string['limituploadjobsdesc'] = 'Limit the count of uploadjobs done by one cronjob. The cronjob can be scheduled here: {$a}';
$string['limitvideos'] = 'Number of videos';
$string['limitvideosdesc'] = 'Maximum number of videos to display in block';
$string['messageprovider:error'] = 'Processing error notification';
$string['missingevent'] = 'Creation of event failed';
$string['missinggroup'] = 'Missing group in opencast';
$string['missingseries'] = 'Missing series in opencast';
$string['missingseriesassignment'] = 'Missing series assignment';
$string['series_already_exists'] = 'This course is already assigned to a series.';
$string['morevideos'] = 'More videos...';
$string['mstatereadytoupload'] = 'Ready to upload';
$string['mstatecreatinggroup'] = 'Creating Opencast Group...';
$string['mstatecreatingseries'] = 'Creating Opencast Series...';
$string['mstatecreatingevent'] = 'Uploading...';
$string['mstateuploaded'] = 'Processing post-upload tasks...';
$string['mstatetransferred'] = 'Transferred';
$string['mstateunknown'] = 'State unknown';
$string['noseriesid'] = 'Series ID is not defined yet.';
$string['nothingtodisplay'] = 'No videos available';
$string['notpublished'] = 'Not published';
$string['novideosavailable'] = 'No videos available';
$string['opencast:addinstance'] = 'Add a new opencast upload block';
$string['opencast:addlti'] = 'Add Opencast series module to course';
$string['opencast:addvideo'] = 'Add a new video to opencast upload block';
$string['opencast:createseriesforcourse'] = 'Create a new series in opencast for a moodle course';
$string['opencast:deleteevent'] = 'Finally delete a video (event) in opencast';
$string['opencast:defineseriesforcourse'] = 'Link an existing opencast series to a moodle course';
$string['opencast:myaddinstance'] = 'Add a new opencast upload block to Dashboard';
$string['opencast:unassignevent'] = 'Unassign a video from the course, where the video was uploaded.';
$string['opencast:viewunpublishedvideos'] = 'View all the videos from opencast server, even when they are not published';
$string['overview'] = 'Overview';
$string['planned'] = 'Planned';
$string['pluginname'] = 'Opencast Videos';
$string['processupload'] = 'Process upload';
$string['processdelete'] = 'Process delete jobs for Opencast';
$string['publishtoengage'] = 'Publish to Engage';
$string['publishtoengagedesc'] = 'Select this option to publish the video after upload to engage player. Setup workflow must support this.';
$string['reuseexistingupload'] = 'Reuse existing uploads';
$string['reuseexistinguploaddesc'] = 'If activated, multiple videos with the same content hash are uploaded to opencast only once.
This saves storage and processing power, but it might cause problems, when you use specific access policies based on opencast series.';
$string['rolename'] = 'Role name';
$string['seriescreated'] = 'Series was created.';
$string['seriesnotcreated'] = 'Series could not be created.';
$string['seriesidsaved'] = 'The series ID was saved.';
$string['seriesidunset'] = 'The series ID was removed.';
$string['seriesidnotvalid'] = 'The series does not exist.';
$string['series_does_not_exist_admin'] = 'The series with the identifier \'{$a}\' could not be retrieved from Opencast.';
$string['series_does_not_exist'] = 'The series assigned to this course is not valid. Please contact your Administrator!';
$string['seriesname'] = 'Series name';
$string['seriesnamedesc'] = 'Series to which the video is added. You can use the placeholders [COURSEID] and [COURSENAME] which are automatically replaced.';
$string['series_name_empty'] = 'Series name must not be empty.';
$string['catalog_static_params_empty'] = "Read only fields need to define a text in the parameters field.";
$string['catalog_params_noarray'] = "Parameters have to be either empty or a JSON representation of an array or an object.";
$string['settings'] = 'Opencast Videos';
$string['general_settings'] = 'General settings';
$string['additional_settings'] = 'Additional features';
$string['setting_permanent'] = 'Is permanent';
$string['show_public_channels'] = 'Show publications channels';
$string['show_public_channels_desc'] = 'If ticked, the users can see the column with the publication channels in the list of the published videos.';
$string['submit'] = 'Save changes';
$string['ocstatefailed'] = 'Failed';
$string['ocstateprocessing'] = 'Processing';
$string['ocstatesucceeded'] = 'Succeeded';
$string['restoreopencastvideos'] = 'Restore opencast videos';
$string['ocstatecapturing'] = 'Capturing';
$string['ocstateneedscutting'] = 'Needs cutting';
$string['uploadfileextensions'] = 'Allowed file extensions';
$string['uploadfileextensionsdesc'] = 'Comma separated list of allowed video file extensions (extensions must exist in Moodle\'s <a href="{$a}">File types</a> list). If left blank all extensions with type group \'video\' are allowed (again see <a href="{$a}">File types</a>).';
$string['uploadingeventfailed'] = 'Creating of event failed';
$string['uploadjobssaved'] = 'Upload jobs saved.';
$string['uploadqueuetoopencast'] = 'Videos currently being uploaded to the streaming server';
$string['uploadworkflow'] = 'Workflow to start after upload';
$string['uploadworkflowdesc'] = 'Setup the unique shortname of the workflow, that should be started after succesfully uploading a video file to opencast.
    If left blank the standard workflow (ng-schedule-and-upload) will be used. Ask for additional workflows that may have been created by the opencast administrator.';
$string['videosavailable'] = 'Videos available in this course';
$string['videonotfound'] = 'Video not found';
$string['videostoupload'] = 'Videos to upload to opencast';
$string['visibility'] = 'Visibility of the video';
$string['visibility_hide'] = 'Prevent any student from accessing the video';
$string['visibility_show'] = 'Allow all students of the course to access the video';
$string['visibility_group'] = 'Allow all students belonging to selected groups to access the video';
$string['workflownotdefined'] = 'The workflow for updating metadata is not defined.';
$string['worklowisrunning'] = 'A workflow is running. You cannot change the visibility at the moment.';
$string['workflowrolesname'] = 'Workflow for changing the ACL rules';
$string['workflowrolesdesc'] = 'This workflow is triggered when the nonpermanent ACL rules are deleted or added. If not set, it will not be possible to change the visibility of uploaded videos through the block.';

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

$string['workflow_not_existing'] = 'This workflow does not exist.';
$string['wrongmimetypedetected'] = 'An invalid mimetype was used while uploading the video {$a->filename} from course {$a->coursename}.
    Only video files are allowed!';
//Added to support metadata and so on
$string['metadata'] = 'Metadata';
$string['mediatype'] = 'Media Source';
$string['title'] = 'Title';
$string['description'] = 'Description';
$string['rights'] = 'Rights';
$string['presenter'] = 'Presenter(s)';
$string['contributor'] = 'Contributor(s)';
$string['source'] = 'Source';
$string['type'] = 'Media Type';
$string['date'] = 'Start Date';
$string['upload'] = 'File Upload';
$string['subjects'] = 'Subjects';
$string['language'] = 'Language';
$string['rightsHolder'] = 'Rights';
$string['license'] = 'License';
$string['creator'] = 'Presenter(s)';
$string['startDate'] = 'Date';
$string['startTime'] = 'Time';
$string['duration'] = 'Duration';
$string['location'] = 'Location';
$string['created'] = 'Created at';
$string['publisher'] = 'Publisher';
$string['identifier'] = 'Identifier';
$string['updatemetadatasaved'] = 'Metadata is saved.';
$string['updatemetadata'] = 'Update metadata for this event';
$string['presenter'] = 'Presenter';
$string['presenterdesc'] = 'A video of a person speaking to an audience.';
$string['presentation'] = 'Presentation';
$string['presentationdesc'] = 'A video of slide presentation (Keynote, Powerpoint, etc).';
$string['emptyvideouploaderror'] = 'At least a video for Presenter Or Slide must be uploaded!';
$string['heading_position'] = 'Position';
$string['heading_name'] = 'Field Name';
$string['heading_datatype'] = 'Field Type';
$string['heading_required'] = 'Required';
$string['heading_readonly'] = 'Read Only';
$string['heading_params'] = 'Parameters (JSON)';
$string['catalogparam'] = 'Parameters in JSON-Format';
$string['catalogparam_help'] = '<b>JSON format:</b> {"param1":"value1", "param2":"value2"}<br>
                                <b>String (text), Long Text (textarea):</b> Parameters will be defined as attributes of the element. i.e. {"style":"min-width: 27ch;"} which defines the element´s style attribute <br>
                                <b>Drop Down (select):</b> Parameters will be defined as options of the select element. i.e. {"en": "English", "de": "German"} which takes the left side as value and right side as text to show<br>
                                <b>Arrays (autocomplete):</b> Parameters will be defined as <a target="_blank" href="https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#autocomplete">suggestions</a>. i.e. {"1": "Dr. Doe", "2": "Johnson"} which shows (Dr. Doe and Johnson) as suggestions<br>
                                <b>Date Time Selector (datetime):</b> Parameters will be defined as <a target="_blank" href="https://docs.moodle.org/dev/lib/formslib.php_Form_Definition#date_selector">date_selector variables</a> . i.e. {"startyear": "1990", "stopyear": "2020"} which defines date range to be selected between 1990 - 2020';
$string['addcatalog'] = 'Add new metadata';
$string['descriptionmdfn'] = 'This is the actual field name passing as metadata (id); the presented name according to this field name should be set in language string.';
$string['descriptionmdpj'] = 'The value should be JSON string format and it is used to define parameters for the field!<br>You may enter <a href="https://docs.moodle.org/en/Multi-language_content_filter">multi-language</a> strings in this format:

    <pre>
    &ltspan lang="en" class="multilang"&gt{"en": "English", "de": "German"}&lt/span&gt
    &ltspan lang="de" class="multilang"&gt{"en": "Englisch", "de": "Deutsch"}&lt/span&gt
    </pre>';
$string['empty_catalogname'] = 'This field must not be empty';
$string['space_catalogname'] = 'This field must not contain space';
$string['exists_catalogname'] = 'The field is already existed';
$string['metadata_autocomplete_placeholder'] = 'Enter {$a}';
$string['metadata_autocomplete_noselectionstring'] = 'No {$a} provided!';
$string['expirydate'] = 'Expiry Date';
$string['filetypes'] = 'Accepted file types';
$string['title'] = 'Title';
$string['presenterfilename'] = 'Filename (Presenter)';
$string['presenterfilesize'] = 'Filesize (Presenter)';
$string['presentationfilename'] = 'Filename (Presentation)';
$string['presentationfilesize'] = 'Filesize (Presentation)';
$string['addnewcatalogfield'] = 'A new field has been added to metadata catalog.';
$string['recordvideo'] = 'Record video';
$string['heading_lti'] = 'Setting for LTI Configuration';
$string['enableopencaststudiolink'] = 'Show the link to opencast studio';
$string['enableopencaststudiolink_desc'] = 'This option renders a button to opencast studio in the block content and the block overview.
Opencast studio has to run on your opencast admin node and the following lti settings have to be configured as well.';
$string['lticonsumerkey'] = 'Consumer key';
$string['lticonsumerkey_desc'] = 'LTI Consumer key for the opencast studio integration.';
$string['lticonsumersecret'] = 'Consumer secret';
$string['lticonsumersecret_desc'] = 'LTI Consumer secret for the opencast studio integration.';
$string['opencaststudiointegration'] = 'Opencast studio integration';
