CHANGELOG
=========

5.0.0 (2025-05-02)
------------------
* [FEATURE] The Opencast Course Overview is now accessible via the course navigation bar
* [CHANGE] Most features from the Opencast Block plugin have been moved to
the Opencast Tool plugin. The Opencast Block plugin is now optional.
* [CHANGE] The course backup functionality has changed. The option to select
individual events has been removed.\
A site-wide admin setting (`importreducedduplication`) has been added.\
If enabled, only events and series embedded via LTI or the Opencast
activity module will be backed up.\
If disabled (default), all events from
the course will be included in the backup.


4.5.3 (2025-01-16)
------------------
* [FIX] #413 Capability check for user before importing series
* [FEATURE] #412 Settings link in Plugins overview and Manage blocks
* [FEATURE] #411 Toggle Add Video(s) buttons during upload when using Chunkuploader
* [FEATURE] #410 New events metadata field location
* [FEATURE] #409 Dynamic video size limitation for chunkuploader setting
* [FEATURE] #405 Use new opencast api exceptions from API plugin


4.5.2 aka 4.5.1 (2024-12-03)
------------------
* [FIX] #404 Mass Action list - fixes
* [FIX] #400 Applying correct default completion while building LTI module
* [FIX] #399 Series Management UI/UX Improvements
* [FIX] #386 Failed upload job limiter
* [FEATURE] #398 Mass Action Support for Video List Tables
* [FEATURE] #390 Workflow configuration panel during upload
* [FEATURE] #373 Support for new transcription management (subtitles) in Opencast 15


4.5.0 (2024-11-12)
------------------
* [FEATURE] #387 Language file ordering
* [FEATURE] #383 Some basic codestyling things
* [FIX] #372 Hidden duplicated videos end up having empty ACLs
Moodle 4.5 compatible version
