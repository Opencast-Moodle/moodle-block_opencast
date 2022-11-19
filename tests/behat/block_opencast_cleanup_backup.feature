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
    And the following config values are set as admin:
      | config                             | value                                                         | plugin         |
      | apiurl_1                           | http://testapi:8080                                           | tool_opencast  |
      | apipassword_1                      | opencast                                                      | tool_opencast  |
      | apiusername_1                      | admin                                                         | tool_opencast  |
      | ocinstances                        | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1                  | 0                                                             | block_opencast |
      | group_creation_1                   | 0                                                             | block_opencast |
      | group_name_1                       | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1                      | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1                | 0                                                             | block_opencast |
      | workflow_roles_1                   | republish-metadata                                            | block_opencast |
      | importvideosenabled_1              | 1                                                             | block_opencast |
      | importvideosmanualenabled_1        | 1                                                             | block_opencast |
      | importmode_1                       | duplication                                                   | block_opencast |
      | duplicateworkflow_1                | duplicate-event                                               | block_opencast |
      | importvideoshandleseriesenabled_1  | 1                                                             | block_opencast |
      | importvideoshandleepisodeenabled_1 | 1                                                             | block_opencast |
      | addltiepisodeenabled_1             | 1                                                             | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > External tool > Manage tools" in site administration
    And I follow "Manage preconfigured tools"
    And I follow "Add preconfigured tool"
    And I set the following fields to these values:
      | Tool name                | Opencast series                 |
      | Tool URL                 | 172.17.0.1:8080/lti             |
      | Custom parameters        | tool=ltitools/series/index.html |
      | Default launch container | Embed, without blocks           |
    And I press "Save changes"
    And I follow "Add preconfigured tool"
    And I set the following fields to these values:
      | Tool name                | Opencast episode                |
      | Tool URL                 | 172.17.0.1:8080/lti             |
      | Custom parameters        | tool=ltitools/player/index.html |
      | Default launch container | Embed, without blocks           |
    And I press "Save changes"
    And I navigate to "Plugins > Blocks > Opencast Videos > LTI module features" in site administration
    And I set the following fields to these values:
      | Enable "Add LTI series module"             | 1                |
      | Default LTI series module title            | Opencast videos  |
      | Preconfigured LTI tool for series modules  | Opencast series  |
      | Enable "Add LTI episode module"            | 1                |
      | Preconfigured LTI tool for episode modules | Opencast episode |
    And I press "Save changes"

  @javascript
  Scenario: Teachers should be able to import a series in the duplicate mode and cleanup LTI modules
    Given I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block
    When I click on "Go to overview..." "link"
    And I click on "Add Opencast LTI series module to course" "link"
    And I click on "Add module and return to overview" "button"
    And I click on "Add Opencast episode module to course" "link"
    And I click on "Add module and return to course" "button"
    And I open "Opencast videos" actions menu
    And I choose "Edit settings" in the open action menu
    Then the field "Custom parameters" matches value "series=1234-1234-1234-1234-1234"
    When I click on "Cancel" "button"
    And I open "Test video" actions menu
    And I choose "Edit settings" in the open action menu
    Then the field "Custom parameters" matches value "id=abcd-abcd-abcd-abcd"
    When I click on "Cancel" "button"
    When I backup "Course 1" course using this options:
      | Confirmation | Filename                                                     | test_backup.mbz |
      | Schema       | Include videos from Opencast instance Default in this course | 0               |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 1 |
    And I click on "Go to overview..." "link"
    And I click on "Import videos" "button"
    And I click on "#import-course-1" "css_element"
    And I click on "Continue" "button"
    And I click on "Continue" "button"
    Then I should see "Yes, clean up the Opencast series modules related to this import"
    And I should see "Yes, clean up the Opencast episode module(s) related to this import"
    And I click on "Continue" "button"
    When I click on "Import videos and return to overview" "button"
    Then I should see "The import of the selected videos into this course was scheduled"
    And I run the scheduled task "\block_opencast\task\cleanup_imported_episodes_cron"
    And I am on "Course 1 copy 1" course homepage with editing mode on
    And I open "Opencast videos" actions menu
    And I choose "Edit settings" in the open action menu
    Then the field "Custom parameters" matches value "series=84bab8de-5688-46a1-9af0-5ce9122eeb6a"
    When I click on "Cancel" "button"
    And I open "Test video" actions menu
    And I choose "Edit settings" in the open action menu
    Then the field "Custom parameters" matches value "id=abcd-abcd-abcd-abcd"
