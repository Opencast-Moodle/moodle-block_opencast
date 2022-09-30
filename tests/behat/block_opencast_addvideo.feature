@block @block_opencast
Feature: Add videos as Teacher
  In order to upload videos to Opencast
  As teacher
  I need to be able to select video files and upload them to Opencast

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
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  Scenario: Opencast Add video page implemented
    Given I click on "Go to overview..." "link"
    When I click on "Add video" "button"
    Then I should see "You can drag and drop files here to add them."

  @_file_upload @javascript
  Scenario: Opencast Upload Video
    Given I click on "Go to overview..." "link"
    And I click on "Add video" "button"
    And I set the field "Title" to "Test"
    And I upload "blocks/opencast/tests/fixtures/test.mp4" file to "Presenter video" filemanager
    And I click on "Add video" "button"
    Then I should see "Test"
    And I should see "test.mp4"
    And I should see "Ready for transfer"
