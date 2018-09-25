moodle-block_opencast
=====================

This block can be used to link moodle courses to opencast series.
Users with respective priviledges (in the following called teacher) can use this block to upload videos via moodle to opencast. 
These videos are transmitted to the opencast system by the cronjob and create a event for the respective series there.
The block can be used to automatically set the access privileges of moodle users enrolled in the course.
All events belonging to the series are displayed in the block. 
This way, the teacher gets an overview of all recorded lectures as well as planned ones.
Further, if setup correctly, the teacher is able to restrict the visibility to moodle groups or prevent access by students at all.
 
System requirements
------------------

* Min. Moodle Version: 3.3
* Opencast API level: 
   * Minimum: v1.0.0
   * Recommended: v1.1.0

   * Some features might do not works as expected, when using an older API level.

* Installed plugin: <a href="https://github.com/unirz-tu-ilmenau/moodle-tool_opencast">tool_opencast</a>

Block overview
--------------
The block has a detail view which displays two lists of videos.
The first list shows the upload jobs which are not completed yet. The current status of the videos is shown in a column.

The second table shows all videos of the series which is associated with the block. 
It is possible that the videos were uploaded by the block or that they were uploaded otherwise.
The table shows the upload date as well as the status.
Video, which are planned to be recorded in the future, are also displayed in this list.
Moreover, the publication channels and the visibility of a video can be displayed.

Usage scenarios
---------------

In the following 3 use cases are described, how the plugin can be used.

### Use case 1 - Upload a video ###
The teacher of a course wants to upload the video "Lecture 1" to his Moodle course.
Prerequisite: The course page already contains an instance of the Block "Opencast Videos".

Steps to upload a video:

1. Clicking on "Add video" leads to an upload form.
2. Here the teacher can upload a video file to moodle:</br>
<img src="https://user-images.githubusercontent.com/9437254/32501009-2996085e-c3d7-11e7-9d4e-c957ba28d467.png" width="250"></br>
3. Saving the form creates an upload job, which is registered to the Upload Job Queue.</br>
<img src="https://user-images.githubusercontent.com/9437254/32501133-7da4fd56-c3d7-11e7-9c1f-fe242345e2c7.png" width="500"></br>
4. The processing of a video is done during a cron job and contain the following steps:
    1. A new series is created in opencast with the name which was set in the settings, if it is not yet created.
    2. A new group is created in opencast with the name which was set in the settings, if it is not yet created. This step is skipped if "Create a group" is not selected in the settings.
    This group control the access to the video.
    3. The video is uploaded to opencast, if it does not yet exist or the config *reuseexistingupload* is false.
    In this step all permanent and non-permanent ACL-Rules are assigned to the video.
    4. The group is assigned to the video.
    
Since most operations in the upload process are done by opencast in an asynchronous fashion, uploading a video can take multiple runs of the cronjob to finish.


## Use case 2 - Change the visibility ###
The teacher of a course wants to hide an uploaded video from the students.
The (opencast) admin has definded student specific roles.

Steps to change the visiblity:

1. Go to the overview section of the block.</br>
<img src="https://user-images.githubusercontent.com/9437254/46022814-9da8e180-c0e3-11e8-9040-848952089afe.png" width="500"></br>
2. Click on the visiblity icon.
3. In the visibility form the teacher can select the needed visibility state.</br>
<img src="https://user-images.githubusercontent.com/9437254/46023014-01cba580-c0e4-11e8-9fe4-d8688cde1423.png" width="500"></br>
4. The processing of the video starts:
    1. The ACL-Roles of the video are changed and updated in Opencast.
    2. A workflow (must be defined in the settings) is started which refreshes the metadata.
    
## Use case 3 - Linking a recorded series ###

A teacher wants to link an existing series to his moodle course and control the access to these videos using the list of students in his moodle course.
For this the series id has to be reference in the block.

1. Go to the overview section of the block.
2. Go to "Edit series mapping".</br>
<img src="https://user-images.githubusercontent.com/9437254/46022815-9da8e180-c0e3-11e8-969c-15e0bbab4417.png" width="500"></br>
3. Enter the series id and save the form.
4. Now all videos of the series should be displayed in the block and the visibility of them can be adjusted.

Configuration
-------------

In the following, we outline the settings for the block. 
Through the settings, the block can be adjusted to the needs of your platform.
Most of the features the block provides, can be further specified or completely turned off. 

The core functionality is to upload a video file to moodle, which is then transferred to opencast.
This is done via a cronjob, which processes all Upload Jobs in a first in first out fashion.

Please make sure that the *Maximum Time limit* for cron execution in *Site administration*->*Server*->*Performance* is not restricted (value of 0 means no timelimit).
Then the cron job is not terminated early.

The plugin <a href="https://github.com/unirz-tu-ilmenau/moodle-tool_opencast">tool_opencast</a> bundles some administration settings for all opencast plugins, which have to be configured first!

#### Settings for upload jobs
In this section you can define the following settings:
- How many videos are uploaded within one run of the cronjob. If it is set to 0, the number of videos is not limited.
- Limit the file size of uploaded videos. This feature is currently only working in combination with some core changes. 
See issue <a href="https://github.com/unirz-tu-ilmenau/moodle-block_opencast/issues/20">#20</a>.
- Setup the unique shortname of the workflow that should be started after successfully uploading a video file to opencast.
- Select whether the videos should be published to engage player. This results in the configuration field 'publishToEngage' to be set to true or false for the called workflow (only useful if the selected workflow supports this).
- Select if multiple videos with the same content hash are uploaded to opencast only once (legacy feature: Not recommended to be used. With our further development we strive to create one series per course.).
- Allow unassign from course others a 'delete' icon to the teacher, which will unassign the event from the series of the course. (legacy feature: Only useful if events should not actually being deleted!) 
- Setup the unique shortname of the workflow that should be started for deleting a video file in opencast. If a workflow is selected, a 'delete' icon is offered to the teacher, which will actually delete the event in the opencast system.
We recommend to use only one of the two previous 'delete' options!
- The setting 'Delete videofile from moodle' causes the moodle system to delete the file of the uploaded video as soon as possible. If set to false, the video file will remain in the moodle system in the draft area until a cron job deletes it (usually some days later). 
 
#### Settings for overview page
In this section you can define how many videos should be displayed in the block.
Additionally, you can define if the publication channels of each video are displayed to the user.

#### Access policies
These settings define metadata for the uploaded videos, e.g. the group or series name.
You can use the placeholders [COURSEID] and [COURSENAME] in these settings. They are automatically replaced with the courseid/name of the course the respective block instance belongs to.

'Create a group' is a legacy feature, which assigns each uploaded event to a opencast group. From our point of view there is no practical use in this.  

#### Roles
You can define which ACL rules are added to a video. 
You can add and delete roles and define the respective actions for these roles.
 
**! This might only be relevant if you want to control the access privileges for your opencast videos via moodle !**
 
Then you have to setup the moodle-role-provider for you opencast system (https://docs.opencast.org/develop/admin/configuration/security.user.moodle/).

It is possible to select if roles should be **permanent** or not.
Non-permanent roles can be used to manually change the access of certain user groups (e.g. students) for each video.
Roles which are not permanent will be deleted if the video is hidden in the block overview and added again if the video is made visible. 

To use this feature, you need to define at least one non-permanent role-action combination and the shortname of opencast workflow for republishing metadata 
(for OC 4.x this could be <a href="https://github.com/opencast/opencast/blob/r/4.x/etc/workflows/ng-republish-metadata.xml">ng-republish-metadata</a>,
for OC 5.x this could be <a href="https://github.com/opencast/opencast/blob/r/5.x/etc/workflows/republish-metadata.xml">republish-metadata</a>).
In the video overview of the block, the teacher is able to change the visibility of each video.
**However, the icon to do so, is only present if the two requirements mentioned above are met!**
This process takes some time, since the opencast workflow needs to finish.

In the ACL Roles the following placeholders can be used: 
 * [COURSEID]: Will be replaced by the id of the course.
 * [COURSEGROUPID]: Should only be used in non-permanent roles. 
 Dependent on the visibility of the event, it is either replaced by the id of the course or 
 (in case the visibility is restricted by groups) it is replaced by a 'G' followed by the id of the group. 
 In case that multiple groups are selected in the visibility dialog, one ACL rule for every group is created.
 The basic role including the course id is removed in the case of group visibility.
    
To give an example for Roles, which also meets the LTI standard, you can use the following setting:

| Role                    | Actions    | Permanent |
| ------------------------|------------|-----------|
| ROLE_ADMIN              | write,read | Yes       |
| [COURSEID]_Instructor   | write,read | Yes       |
| [COURSEGROUPID]_Learner | read       | No        |
    

Capabilities
------------
There are additional capabilities, with which you can control the access to the features of the block.

| Capability                           | Role in default configuration | Description                                                                                          |
|--------------------------------------|-------------------------------|------------------------------------------------------------------------------------------------------|
| block/opencast:addvideo              | editingteacher, manager       | Add a video via moodle to opencast.                                                                   |
| block/opencast:viewunpublishedvideos | editingteacher, manager       | View the list of all videos of the course, which are available in opencast (even not published ones) |
| block/opencast:defineseriesforcourse | manager       | Change the series ID which is associated with the block instance or rather course |
| block/opencast:createseriesforcourse | manager       | Create a new series if block/course is not yet associated with one |
| block/opencast:deleteevent           | editingteacher, manager       | Allows to delete a video as specified above. |
| block/opencast:unassignevent         | -                              | Allows to unassign a video from the series of the course as specified above. |

Logging
-------
The execution of upload jobs are being logged, which can be viewed at *Site administration*->*Reports*->*Logs*.
View the setting "Site Errors" instead of "All activities" you can view only those upload jobs, which failed.    

## License ##

This plugin is developed in cooperation with the TU Ilmenau and the WWU Münster.

It is based on 2017 Andreas Wagner, SYNERGY LEARNING

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.