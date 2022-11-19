@block @block_opencast
Feature: Add Opencast block as Teacher
  Overview and Add video / Edit upload tasks Page exists

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
      | apiurl_1            | http://172.17.0.1:8080                                        | tool_opencast  |
      | apipassword_1       | opencast                                                      | tool_opencast  |
      | apiusername_1       | admin                                                         | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1   | 0                                                             | block_opencast |
      | group_creation_1    | 0                                                             | block_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1 | 0                                                             | block_opencast |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  Scenario: The Opencast Videos block is Added
    Then I should see "No videos available"

  Scenario: Opencast Overview page implemented
    When I click on "Go to overview..." "link"
    Then I should not see "Videos scheduled to be transferred to Opencast"
    And I should see "Videos available in this course"
    And I should see "Currently, no videos have been uploaded to this course yet."
