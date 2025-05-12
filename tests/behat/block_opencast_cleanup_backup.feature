@block @block_opencast @block_opencast_cleanup
Feature: Restore courses as Teacher
  In order to reuse courses in the next semester
  As teacher
  I need to be able to backup Opencast videos, import them and fix LTI modules

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | Teacher   | 1        | teacher1@example.com | T1       |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I setup the default settigns for opencast plugins
    And the following config values are set as admin:
      | config                             | value                                                         | plugin         |
      | apiurl_1                           | http://testapi:8080                                           | tool_opencast  |
      | apipassword_1                      | opencast                                                      | tool_opencast  |
      | apiusername_1                      | admin                                                         | tool_opencast  |
      | ocinstances                        | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1                  | 0                                                             | tool_opencast |
      | group_creation_1                   | 0                                                             | tool_opencast |
      | group_name_1                       | Moodle_course_[COURSEID]                                      | tool_opencast |
      | series_name_1                      | Course_Series_[COURSEID]                                      | tool_opencast |
      | enablechunkupload_1                | 0                                                             | tool_opencast |
      | workflow_roles_1                   | republish-metadata                                            | tool_opencast |
      | importvideosenabled_1              | 1                                                             | tool_opencast |
      | importvideoscoreenabled_1          | 1                                                             | tool_opencast |
      | importvideosmanualenabled_1        | 1                                                             | tool_opencast |
      | importmode_1                       | duplication                                                   | tool_opencast |
      | duplicateworkflow_1                | duplicate-event                                               | tool_opencast |
      | importvideoshandleseriesenabled_1  | 1                                                             | tool_opencast |
      | importvideoshandleepisodeenabled_1 | 1                                                             | tool_opencast |
      | addltiepisodeenabled_1             | 1                                                             | tool_opencast |
      | importvideoscoredefaultvalue_1     | 1                                                             | tool_opencast |
      | enableasyncbackup                  | 0                                                             |                |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > External tool > Manage tools" in site administration
    And I follow "Manage preconfigured tools"
    And I follow "Add preconfigured tool"
    And I set the following fields to these values:
      | Tool name                | Opencast series                 |
      # The url here is only designed for the test environment in github ci.
      | Tool URL                 | 172.17.0.1:8080/lti             |
      | Custom parameters        | tool=ltitools/series/index.html |
      | Default launch container | Embed, without blocks           |
    And I press "Save changes"
    And I follow "Add preconfigured tool"
    And I set the following fields to these values:
      | Tool name                | Opencast episode                |
      # The url here is only designed for the test environment in github ci.
      | Tool URL                 | 172.17.0.1:8080/lti             |
      | Custom parameters        | tool=ltitools/player/index.html |
      | Default launch container | Embed, without blocks           |
    And I press "Save changes"
    And I navigate to "Plugins > Admin tools > Opencast API > LTI module features" in site administration
    And I set the following fields to these values:
      | Enable "Add LTI series module"             | 1                |
      | Default LTI series module title            | Opencast videos  |
      | Preconfigured LTI tool for series modules  | Opencast series  |
      | Enable "Add LTI episode module"            | 1                |
      | Preconfigured LTI tool for episode modules | Opencast episode |
    And I press "Save changes"

  @javascript
  Scenario: Teachers should be able to import a series in duplicate mode and cleanup LTI modules, Admin should be notified on errors
    Given I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block
    When I click on "Go to overview..." "link"
    And I click on "Add Opencast LTI series module to course" "link"
    And I click on "Add module and return to overview" "button"
    And I click on "Add Opencast episode module to course" "link"
    And I click on "Add module and return to course" "button"
    Then the lti tool "Opencast videos" should have the custom parameter "series=1234-1234-1234-1234-1234"
    And the lti tool "Test video" should have the custom parameter "id=abcd-abcd-abcd-abcd"
    When I backup "Course 1" course using this options:
      | Confirmation | Filename                                                     | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 1 |
    And I click on "Go to overview..." "link"
    And I click on "Import videos" "button"
    And I click on "#import-course-1" "css_element"
    And I click on "Continue" "button"
    And I click on "Continue" "button"
    And I should see "Yes, clean up the Opencast episode module(s) related to this import"
    And I click on "Continue" "button"
    When I click on "Import videos and return to overview" "button"
    Then I should see "The import of the selected videos into this course was scheduled"
    And I run the scheduled task "\tool_opencast\task\cleanup_imported_episodes_cron"
    And I am on "Course 1 copy 1" course homepage with editing mode on
    Then the lti tool "Opencast videos" in the course "Course 1 copy 1" should have the custom parameter "series=84bab8de-5688-46a1-9af0-5ce9122eeb6a"
    Then the lti tool "Test video" in the course "Course 1 copy 1" should have the custom parameter "id=abcd-abcd-abcd-abcd"
    And I run all adhoc tasks
    And the following config values are set as admin:
      | config   | value | plugin        |
      | apiurl_1 |       | tool_opencast |
    And I run the scheduled task "\tool_opencast\task\cleanup_imported_episodes_cron"
    And the following config values are set as admin:
      | config   | value               | plugin        |
      | apiurl_1 | http://testapi:8080 | tool_opencast |
    And I reload the page
    When I click on ".popover-region-notifications" "css_element"
    Then I should see "Opencast imported modules cleanup task notification"
    When I click on ".all-notifications .notification" "css_element"
    Then I should see "Cleanup job with workflow id:"
