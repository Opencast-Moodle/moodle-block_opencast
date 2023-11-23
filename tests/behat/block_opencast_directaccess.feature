@block @block_opencast
Feature: Direct Access via shared link

  Background:
    Given the following "users" exist:
      | username      | firstname | lastname | email                | idnumber |
      | teacher1      | Teacher   | 1        | teacher1@example.com | T1       |
      | student1      | Student   | 1        | s1@example.com       | STD1     |
      | gueststudent1 | Guest     | Student  | gs2@example.com      | GSTD2    |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user          | course | role           |
      | teacher1      | C1     | editingteacher |
      | student1      | C1     | student        |
      | gueststudent1 | C1     | guest          |
    And I setup the default settigns for opencast plugins
    And the following config values are set as admin:
      | config                  | value                                                         | plugin         |
      # Because we want to get the advantage of the using LTI we need to use the stable.opencast.org
      | apiurl_1                | https://stable.opencast.org                                   | tool_opencast  |
      | apipassword_1           | opencast                                                      | tool_opencast  |
      | apiusername_1           | admin                                                         | tool_opencast  |
      | lticonsumerkey_1        | CONSUMERKEY                                                   | tool_opencast  |
      | lticonsumersecret_1     | CONSUMERSECRET                                                | tool_opencast  |
      | ocinstances             | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1       | 0                                                             | block_opencast |
      | group_creation_1        | 0                                                             | block_opencast |
      | group_name_1            | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1           | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1     | 0                                                             | block_opencast |
      | workflow_roles_1        | republish-metadata                                            | block_opencast |
      | direct_access_channel_1 | engage-player                                                 | block_opencast |
      | liveupdateenabled_1     | 0                                                             | block_opencast |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on "Import series" "button"
    And I set the field "Series ID" to "ID-blender-foundation"
    And I click on "Import series" "button" in the ".modal" "css_element"
    And I wait "2" seconds
    And I click on "Opencast Videos" "link"
    And I wait until no video is being processed
    And I wait "1" seconds
    And I reload the page
    And I log out

  @javascript
  Scenario: Teacher should be able to copy and share direct access link. Student enrolled in the course should only be able to access the video.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    And I click on "#opencast-videos-table-ID-blender-foundation_r0 .c3 .access-action-menu a" "css_element"
    Then I should see "Presenter"
    When I click on "#opencast-videos-table-ID-blender-foundation_r0 .c3 .access-action-menu a.access-link-copytoclipboard" "css_element"
    Then I should see "The direct access link has been successfully copied to clipboard."
    When I go to direct access link
    And I wait "1" seconds
    Then I should watch the video in opencast
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I go to direct access link
    And I wait "1" seconds
    Then I should watch the video in opencast
    And I log out
    And I log in as "gueststudent1"
    And I am on "Course 1" course homepage
    And I go to direct access link
    And I wait until the page is ready
    Then I should see "Sorry, but you do not currently have permissions to do that (Direct access to video via shared link)"
