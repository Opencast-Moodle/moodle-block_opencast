@block @block_opencast
Feature: Delete draft
  In order to stop upload jobs
  As admins
  Teachers need to be able to delete upload jobs before uploading videos to Opencast

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
      | config              | value                                                         | plugin         |
      | apiurl_1            | http://172.17.0.1:8080                                        | tool_opencast  |
      | apipassword_1       | opencast                                                      | tool_opencast  |
      | apiusername_1       | admin                                                         | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1   | 0                                                             | tool_opencast |
      | group_creation_1    | 0                                                             | tool_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                      | tool_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                      | tool_opencast |
      | enablechunkupload_1 | 0                                                             | tool_opencast |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @_file_upload @javascript
  Scenario: Delete a video draft
    Given I click on "Go to overview..." "link"
    And I click on "Add video" "button"
    And I set the field "Title" to "Test"
    And I upload "blocks/opencast/tests/fixtures/test.mp4" file to "Presenter video" filemanager
    And I click on "Add video" "button"
    Then I should see "test.mp4"
    When I click on ".generaltable i.fa-trash-can" "css_element"
    Then I should see "Delete video before transfer to Opencast"
    And I click on "Continue" "button"
    Then I should see "The video is deleted successfully"
    And I should not see "test.mp4"
