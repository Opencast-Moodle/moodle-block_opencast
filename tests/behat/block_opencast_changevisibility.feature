@block @block_opencast
Feature: Change visibility
  In order to hide/show videos to students
  As admins
  Teachers need to be able to control the visibility of videos

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
      | config              | value                                                         | plugin         |
      | apiurl_1            | http://testapi:8080                                           | tool_opencast  |
      | apipassword_1       | opencast                                                      | tool_opencast  |
      | apiusername_1       | admin                                                         | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1   | 0                                                             | block_opencast |
      | group_creation_1    | 0                                                             | block_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1 | 0                                                             | block_opencast |
      | workflow_roles_1    | republish-metadata                                            | block_opencast |
      | aclcontrolafter_1   | 1                                                             | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: Change the visibility from "invsible" to "visible"
    Given I click on "Go to overview..." "link"
    And I click on "#opencast-videos-table-1234-1234-1234-1234-1234_r0 i.fa-eye-slash" "css_element"
    And I click on "#id_visibility_1" "css_element"
    And I click on "Save changes" "button"
    Then I should not see "Error"
    And I should see "The change of visibility has been triggered to allow all students of the course to access the video"
