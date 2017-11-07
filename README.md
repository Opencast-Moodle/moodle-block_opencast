moodle-block_opencast
=====================

Users with respective priviledges (e.g. teachers) can use this block to upload videos to moodle. 
These videos are transmitted to the opencast system by the cronjob and create a respective event there.
 
System requirements
------------------

Min. Version: Moodle 3.1

Installed plugin: <a href="https://github.com/unirz-tu-ilmenau/moodle-repository_opencast">repository_opencast</a>

Configuration
-------------

In the settings of the block you need to insert the required data for uploading a video in the opencast system:

<img src="https://user-images.githubusercontent.com/9437254/32501590-b203b33e-c3d8-11e7-8ee7-5431d1e838c6.png" width="500"></br>

Make sure that the API user you define here has the necessary access rights in opencast to actually access the API endpoints for *events*, *groups* and *series*.

After uploading a video file to moodle, the video has to be transferred to opencast.
This is done via a cronjob, which processes all Upload Jobs in a first in first out fashion.
Within the settings of the block you can define, how many videos are uploaded within one run of the cronjob.
The interval of the cronjob can be defined as usual at *Site administration*->*Server*->*Scheduled Tasks*.
The setting *reuseexistingupload* defines, what should happen if the same video is uploaded to moodle twice. 
If activated, videos are uploaded only once. This saves storage and processing power, but it might cause problems, when you use specific access policies based on opencast series. 

<img src="https://user-images.githubusercontent.com/9437254/32499428-d523ba18-c3d2-11e7-9516-b1881eb202f7.png" width="500"></br>

Please make sure that the *Maximum Time limit* for cron execution in *Site administration*->*Server*->*Performance* is not restricted (value of 0 means no timelimit).
Then the cron job is not terminated early.

<img src="https://user-images.githubusercontent.com/9437254/32499675-87784c6a-c3d3-11e7-8ae9-56257b53653c.png" width="500"></br>

Please visit <a href="https://github.com/unirz-tu-ilmenau/moodle-repository_opencast">repository_opencast</a> for the configuration of the repository plugin.

Capabilities
------------

| Capability                           | Role in default configuration | Description                                                                                          |
|--------------------------------------|-------------------------------|------------------------------------------------------------------------------------------------------|
| block/opencast:addvideo              | editingteacher, manager       | Add a video via moodle to opencast                                                                   |
| block/opencast:viewunpublishedvideos | editingteacher, manager       | View the list of all videos of the course, which are available in opencast (even not published ones) |

Logging
-------
The execution of upload jobs are being logged, which can be viewed at *Site administration*->*Reports*->*Logs*.
View the setting "Site Errors" instead of "All activities" you can view only those upload jobs, which failed.

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
    1. A new series is created in opencast with the name "Course_Series_[ID of the course]", if it is not yet created.
    2. A new group is created in opencast with the name "ROLE_GROUP_MOODLE_COURSE_[ID of the course]", if it is not yet created.
    This group control the access to the video.
    3. The video is uploaded to opencast, if it does not yet exist or the config *reuseexistingupload* is false.
    4. The group is assigned to the video.
    
Since most operations in the upload process are done by opencast in an asynchronous fashion, uploading a video can take multiple runs of the cronjob to finish.
