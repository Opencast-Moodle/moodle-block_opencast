@block @block_opencast
Feature: Delete videos

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
      | config              | value                    | plugin         |
      | apiurl              | http://172.17.0.1:8080   | tool_opencast  |
      | apipassword         | opencast                 | tool_opencast  |
      | apiusername         | admin                    | tool_opencast  |
      | limituploadjobs_1   | 0                        | block_opencast |
      | group_creation_1    | 0                        | block_opencast |
      | group_name_1        | Moodle_course_[COURSEID] | block_opencast |
      | series_name_1       | Course_Series_[COURSEID] | block_opencast |
      | enablechunkupload_1 | 0                        | block_opencast |
      | support_email_1     | test@test.de             | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: A teacher should be able to delete a video
    When I click on "Go to overview..." "link"
    And I click on "#opencast-videos-table-1234-1234-1234-1234-1234_r0 i.fa-trash" "css_element"
    And I click on "Delete video permanently" "button"
    Then I should see "The video will be deleted shortly"

  @javascript
  Scenario: A teacher should be able to delete a video when a workflow must be executed before deletion
    Given the following config values are set as admin:
      | config           | value  | plugin         |
      | deleteworkflow_1 | delete | block_opencast |
    When I click on "Go to overview..." "link"
    And I click on "#opencast-videos-table-1234-1234-1234-1234-1234_r0 i.fa-trash" "css_element"
    And I click on "Delete video permanently" "button"
    Then I should see "The video will be deleted shortly"

