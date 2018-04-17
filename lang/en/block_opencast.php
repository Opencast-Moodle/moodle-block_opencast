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
defined('MOODLE_INTERNAL') || die();

$string['aclgroupdeleted'] = 'Access deleted for video: {$a->title}';
$string['accesspolicies'] = 'Access Policies';
$string['aclrolesname'] = 'Roles';
$string['aclrolesnamedesc'] = 'You can use the placeholder [COURSEID] in the role name which is automatically replaced.';
$string['actions'] = 'Comma-separated list of actions';
$string['addvideo'] = 'Add video';
$string['addrole'] = 'Add new role';
$string['apipasswordempty'] = 'Password for API user is not setup correctly, go to settings of block opencast to fix this';
$string['apipasswordempty'] = 'Url for Opencast API is not setup correctly, go to settings of block opencast to fix this';
$string['apiusernameempty'] = 'Username for Opencast API user is not setup correctly, go to settings of block opencast to fix this';
$string['countfailed'] = 'Failed';
$string['createdby'] = 'Created by';
$string['cronsettings'] = 'Settings for upload jobs';
$string['deleteaclgroup'] = 'Delete video from this list.';
$string['delete_confirm'] = 'Are you sure you want to delete this role?';
$string['deletegroupacldesc'] = 'You are about to delete the access to this video from this course.
    If the access is deleted, the video is not displayed in filepicker and in the list of available videos. This does not affect videos, which are already embedded.
    The video will not be deleted in Opencast.';
$string['dodeleteaclgroup'] = 'Delete access to videos from this course';
$string['edituploadjobs'] = 'Edit video upload jobs';
$string['editseriesforcourse'] = 'Edit series';
$string['eventuploadsucceeded'] = 'Upload succeeded';
$string['eventuploadfailed'] = 'Upload failed';
$string['errorgetblockvideos'] = 'Videos cannot be retrieved (Error: {$a})';
$string['form_seriesid'] = 'Series ID';
$string['gotooverview'] = 'Go to overview...';
$string['groupcreation'] = 'Create a group';
$string['groupcreationdesc'] = 'If checked, a group is created during the upload.';
$string['groupname'] = 'Group name';
$string['groupnamedesc'] = 'Group to which the video is added. Important: The group name length is restricted to 128 Bytes. You can use the placeholders [COURSEID] and [COURSENAME] which are automatically replaced.';
$string['heading_role'] = 'Role';
$string['heading_actions'] = 'Actions';
$string['heading_delete'] = 'Delete';
$string['hstart_date'] = 'Date';
$string['htitle'] = 'Name';
$string['hworkflow_state'] = 'Status';
$string['hpublished'] = 'Published';
$string['invalidacldata'] = 'Invalid acl data';
$string['limituploadjobs'] = 'Limit upload job by cron';
$string['limituploadjobsdesc'] = 'Limit the count of uploadjobs done by one cronjob. The cronjob can be scheduled here: {$a}';
$string['limitvideos'] = 'Number of videos';
$string['limitvideosdesc'] = 'Number of videos to display in block';
$string['missingevent'] = 'Creation of event failed';
$string['missinggroup'] = 'Missing group in opencast';
$string['missingseries'] = 'Missing series in opencast';
$string['missingseriesassignment'] = 'Missing series assignment';
$string['morevideos'] = 'More videos...';
$string['mstatereadytoupload'] = 'Ready to upload';
$string['mstatecreatinggroup'] = 'Creating Opencast Group...';
$string['mstatecreatingseries'] = 'Creating Opencast Series...';
$string['mstatecreatingevent'] = 'Uploading...';
$string['mstateuploaded'] = 'Processing post-upload tasks...';
$string['mstatetransferred'] = 'Transferred';
$string['mstateunknown'] = 'State unknown';
$string['noseriesid'] = 'Series ID is not defined yet.';
$string['notpublished'] = 'Not published';
$string['novideosavailable'] = 'No videos available';
$string['opencast:addinstance'] = 'Add a new opencast upload block';
$string['opencast:addvideo'] = 'Add a new video to opencast upload block';
$string['opencast:myaddinstance'] = 'Add a new opencast upload block to Dashboard';
$string['opencast:viewunpublishedvideos'] = 'View all the videos from opencast server, even when they are not pusblished';
$string['overview'] = 'Overview';
$string['overviewsettings'] = 'Settings for overview page';
$string['pluginname'] = 'Opencast Videos';
$string['processupload'] = 'Process upload';
$string['publishtoengage'] = 'Publish to Engage';
$string['publishtoengagedesc'] = 'Select this option to publish the video after upload to engage player. Setup workflow must support this.';
$string['reuseexistingupload'] = 'Reuse existing uploads';
$string['reuseexistinguploaddesc'] = 'If activated, multiple videos with the same content hash are uploaded to opencast only once.
This saves storage and processing power, but it might cause problems, when you use specific access policies based on opencast series.';
$string['rolename'] = 'Role name';
$string['seriesidsaved'] = 'The series was saved.';
$string['seriesidnotvalid'] = 'The series does not exist.';
$string['series_exists'] = 'The series with the identifier \'{$a}\' could not be retrieved from Opencast. Please contact your system administrator.';
$string['seriedidsaved'] = 'Series ID saved.';
$string['seriesname'] = 'Series name';
$string['seriesnamedesc'] = 'Series to which the video is added. You can use the placeholders [COURSEID] and [COURSENAME] which are automatically replaced.';
$string['settings'] = 'Opencast Videos';
$string['submit'] = 'Save changes';
$string['ocstatefailed'] = 'Failed';
$string['ocstateprocessing'] = 'Processing';
$string['ocstatesucceeded'] = 'Succeeded';
$string['uploadfilelimit'] = 'Video size limit';
$string['uploadfilelimitdesc'] = 'Limit the file size of uploaded videos.';
$string['uploadingeventfailed'] = 'Creating of event failed';
$string['uploadjobssaved'] = 'Upload jobs saved.';
$string['uploadqueuetoopencast'] = 'Videos ready to transfer to opencast';
$string['uploadworkflow'] = 'Workflow to start after upload';
$string['uploadworkflowdesc'] = 'Setup the unique shortname of the workflow, that should be started after succesfully uploading a video file to opencast.
    If left blank the standard workflow (ng-schedule-and-upload) will be used. Ask for additional workflows that may have been created by the opencast administrator.';
$string['videosavailable'] = 'Opencast videos released for this course';
$string['videonotfound'] = 'Video not found';
$string['videostoupload'] = 'Videos to upload to opencast';
