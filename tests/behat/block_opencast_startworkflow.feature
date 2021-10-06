@block @block_opencast
Feature: Start workflows for a video as Teacher

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
      | apiurl              | http://172.17.0.1:8080                                        | tool_opencast  |
      | apipassword         | opencast                                                      | tool_opencast  |
      | apiusername         | admin                                                         | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1   | 0                                                             | block_opencast |
      | group_creation_1    | 0                                                             | block_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1 | 0                                                             | block_opencast |
      | workflow_tag_1      | archive                                                       | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: When the update metadata form is loaded, the video metadata are loaded in the form
    When I click on "Go to overview..." "link"
    And I click on "#opencast-videos-table-1234-1234-1234-1234-1234_r0 .c6 .action-menu a" "css_element"
    And I click on "Start workflow" "link"
    Then I should see "Choose the workflow you want to start"
    When I set the field "workflow" to "duplicate-event"
    And I click on "Start workflow" "button"
    Then I should see "Workflow successfully started"
