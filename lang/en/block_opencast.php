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
$string['blocksettings'] = 'Settings for a block instance';
$string['countfailed'] = 'Error';
$string['createdby'] = 'Created by';
$string['createseriesforcourse'] = 'Create new series';
$string['cronsettings'] = 'Settings for upload jobs';
$string['deleteaclgroup'] = 'Delete video from this list.';
$string['delete_confirm'] = 'Are you sure you want to delete this role?';
$string['deleteevent'] = 'Delete a event in opencast';
$string['deleteeventdesc'] = 'You are about to delete this video permanently and irreversibly from opencast.
    All embedded links to it will become invalid. Please do not continue unless you are absolutely sure.';
$string['deletegroupacldesc'] = 'You are about to delete the access to this video from this course.
    If the access is deleted, the video is not displayed in filepicker and in the list of available videos. This does not affect videos, which are already embedded.
    The video will not be deleted in Opencast.';
$string['deleteworkflow'] = 'Workflow to start before event is be deleted';
$string['deleteworkflowdesc'] = 'Before deleting a video, a workflow can be defined, which is called to retract the event from all publication channels.';
$string['dodeleteaclgroup'] = 'Delete access to videos from this course';
$string['editseriesforcourse'] = 'Edit series mapping';
$string['form_seriesid'] = 'Series ID';
$string['form_seriestitle'] = 'Series title';
$string['dodeleteevent'] = 'Delete video permanently';
$string['deleting'] = 'Going to be deleted';
$string['edituploadjobs'] = 'Add video / Edit upload tasks';
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
$string['opencast:addvideo'] = 'Add a new video to opencast upload block';
$string['opencast:createseriesforcourse'] = 'Create a new series in opencast for a moodle course';
$string['opencast:deleteevent'] = 'Finally delete a video (event) in opencast';
$string['opencast:defineseriesforcourse'] = 'Link an existing opencast series to a moodle course';
$string['opencast:myaddinstance'] = 'Add a new opencast upload block to Dashboard';
$string['opencast:unassignevent'] = 'Unassign a video from the course, where the video was uploaded.';
$string['opencast:viewunpublishedvideos'] = 'View all the videos from opencast server, even when they are not pusblished';
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
$string['seriesnotcreated'] = 'Series could not be was created.';
$string['seriesidsaved'] = 'The series ID was saved.';
$string['seriesidunset'] = 'The series ID was removed.';
$string['seriesidnotvalid'] = 'The series does not exist.';
$string['series_does_not_exist_admin'] = 'The series with the identifier \'{$a}\' could not be retrieved from Opencast.';
$string['series_does_not_exist'] = 'The series assigned to this course is not valid. Please contact your Administrator!';
$string['seriesname'] = 'Series name';
$string['seriesnamedesc'] = 'Series to which the video is added. You can use the placeholders [COURSEID] and [COURSENAME] which are automatically replaced.';
$string['series_name_empty'] = 'Series name must not be empty.';
$string['settings'] = 'Opencast Videos';
$string['setting_permanent'] = 'Is permanent';
$string['show_public_channels'] = 'Show publications channels';
$string['show_public_channels_desc'] = 'If ticked, the users can see the column with the publication channels in the list of the published videos.';
$string['submit'] = 'Save changes';
$string['ocstatefailed'] = 'Failed';
$string['ocstateprocessing'] = 'Processing';
$string['ocstatesucceeded'] = 'Succeeded';
$string['ocstatecapturing'] = 'Capturing';
$string['ocstateneedscutting'] = 'Needs cutting';
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
