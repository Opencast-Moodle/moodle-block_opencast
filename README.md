moodle-block_opencast
=====================

Users with respective priviledges (e.g. teachers) can use this block to upload videos to moodle. 
These videos are transmitted to the opencast system by the cronjob and create a respective event there.
 
System requirements
------------------

Min. Version: Moodle 3.1

Installed plugin: <a href="https://github.com/unirz-tu-ilmenau/moodle-tool_opencast">tool_opencast</a>

Configuration
-------------

In the settings of the block you need to insert the required data for uploading a video in the opencast system:

After uploading a video file to moodle, the video has to be transferred to opencast.
This is done via a cronjob, which processes all Upload Jobs in a first in first out fashion.

Please make sure that the *Maximum Time limit* for cron execution in *Site administration*->*Server*->*Performance* is not restricted (value of 0 means no timelimit).
Then the cron job is not terminated early.

#### Settings for upload jobs
In this section you can define the following settings:
- How many videos are uploaded within one run of the cronjob
- Limit the file size of uploaded videos.
- Setup the unique shortname of the workflow that should be started after successfully uploading a video file to opencast.
- Select whether the videos should be published to engage player (if the setup workflow supports this)
- Select if multiple videos with the same content hash are uploaded to opencast only once.

#### Settings for overview page
In this section you can define how many videos should be displayed in the block.

#### Access policies
These settings define metadata for the uploaded videos, e.g. the group or series name.
You can use the placeholders [COURSEID] and [COURSENAME] in these settings. They are automatically replaced with the course id/name where the block was added.

#### Roles
You can define which roles are added to a video. You can add and delete roles and define the actions. Moreover, it is possible to select if roles should be permanent or not.
Roles which are not permanent will be deleted if the video is hidden in the block overview. 

You can use this mechanism e.g. to hide or show a video for students.

Additionally, you have to define which workflow is triggered after changing the metadata (e.g. when a teacher changes the visibility).


Capabilities
------------

| Capability                           | Role in default configuration | Description                                                                                          |
|--------------------------------------|-------------------------------|------------------------------------------------------------------------------------------------------|
| block/opencast:addvideo              | editingteacher, manager       | Add a video via moodle to opencast                                                                   |
| block/opencast:viewunpublishedvideos | editingteacher, manager       | View the list of all videos of the course, which are available in opencast (even not published ones) |
| block/opencast:defineseriesforcourse | manager       | Change the series ID which is associated with the block instance or rather course |
| block/opencast:createseriesforcourse | manager       | Create a new series if block/course is not yet associated with one |

Logging
-------
The execution of upload jobs are being logged, which can be viewed at *Site administration*->*Reports*->*Logs*.
View the setting "Site Errors" instead of "All activities" you can view only those upload jobs, which failed.

Block overview
--------------
The block has a detail view which displays two lists of videos.
The first list shows the upload jobs which are not completed yet. The current status of the videos is shown in a column.

The second table shows all videos of the series which is associated with the block. It is possible that the videos were uploaded by the block or that they were uploaded otherwise.
The table shows the upload date as well as the status. 
Moreover, the publication channels and the visibility of a video can be displayed.

Usage scenarios
---------------

In the following 4 use cases are described, how the plugin can be used.

### Use case 1 - Upload a video ###
The teacher of a course wants to upload the video "Lecture 1" to his Moodle course.
Prerequisite: The course page already contains an instance of the Block "Opencast Videos".

Steps to upload a video:

1. Clicking on "Add video" leads to an upload form.
2. Here the teacher can upload a video file to moodle:
<img src="https://user-images.githubusercontent.com/9437254/32501009-2996085e-c3d7-11e7-9d4e-c957ba28d467.png" width="250"></br>
3. Saving the form creates an upload job, which is registered to the Upload Job Queue.
<img src="https://user-images.githubusercontent.com/9437254/32501133-7da4fd56-c3d7-11e7-9c1f-fe242345e2c7.png" width="500"></br>
4. The processing of a video is done during a cron job and contain the following steps:
    1. A new series is created in opencast with the name which was set in the settings, if it is not yet created.
    2. A new group is created in opencast with the name which was set in the settings, if it is not yet created. This step is skipped if "Create a group" is not selected in the settings.
    This group control the access to the video.
    3. The video is uploaded to opencast, if it does not yet exist or the config *reuseexistingupload* is false.
    4. The group is assigned to the video.
    
Since most operations in the upload process are done by opencast in an asynchronous fashion, uploading a video can take multiple runs of the cronjob to finish.


## Use case 2 - Change the visibility ###
The teacher of a course wants to hide an uploaded video from the students.
The (opencast) admin has definded student specific roles.

Steps to change the visiblity:

1. Go to the overview section of the block.
2. Click on the visiblity icon.
3. The processing of the video starts:
    1. The ACL-Roles of the video are changed and updated in Opencast.
    2. A workflow (must be defined in the settings) is started which refreshes the metadata.