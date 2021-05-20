moodle-block_opencast
=====================

This block can be used to link moodle courses to opencast series.
Users with respective privileges (in the following called teacher) can use this block to upload videos via moodle to opencast. 
These videos are transmitted to the opencast system by the cronjob and create a event for the respective series there.
The block can be used to automatically set the access privileges of moodle users enrolled in the course.
All events belonging to the series are displayed in the block. 
This way, the teacher gets an overview of all recorded lectures as well as planned ones.
Further, if setup correctly, the teacher is able to restrict the visibility to moodle groups or prevent access by students at all.
 
System requirements
------------------

* Min. Moodle Version: 3.3
* Opencast API level:
   * Minimum: v1.1.0

   * Some features might do not works as expected, when using an older API level.

* Installed plugin: <a href="https://github.com/unirz-tu-ilmenau/moodle-tool_opencast">tool_opencast</a>

Features
------------------
* Upload videos to Opencast
* Record videos using Opencast studio
* Overview of recorded and planned videos in the course
* Download processed videos from Opencast
* Edit metadata and delete videos
* Restrict the visibility of videos to moodle groups or prevent access by students at all
* Allow teachers to start Opencast workflows for videos
* Linking an existing Opencast series to the course
* Import videos from other moodle courses by duplicating them
* Report problems to a support team with automatically including technical information
* In integration with the activity [Opencast Video Provider](https://moodle.org/plugins/mod_opencast):
    + Provide the series or single videos as activity for students to watch directly in moodle
    + Restrict the visibility using the extensive moodle activity access settings

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

## Documentation ##
The full documentation is available at [https://moodle.docs.opencast.org/#block/about/](https://moodle.docs.opencast.org/#block/about/).

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
