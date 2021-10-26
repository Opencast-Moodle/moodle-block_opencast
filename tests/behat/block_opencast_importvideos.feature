@block @block_opencast @block_opencast_importvideos
Feature: Import videos as Teacher
  In order to reuse videos from other courses
  As teacher
  I need to be able to import videos from these courses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | Teacher   | 1        | teacher1@example.com | T1       |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |
    And the following config values are set as admin:
      | config                      | value                                                         | plugin         |
      | apiurl                      | http://testapi:8080                                           | tool_opencast  |
      | apipassword                 | opencast                                                      | tool_opencast  |
      | apiusername                 | admin                                                         | tool_opencast  |
      | ocinstances                 | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1           | 0                                                             | block_opencast |
      | group_creation_1            | 0                                                             | block_opencast |
      | group_name_1                | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1               | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1         | 0                                                             | block_opencast |
      | workflow_roles_1            | republish-metadata                                            | block_opencast |
      | importvideosenabled_1       | 1                                                             | block_opencast |
      | importvideosmanualenabled_1 | 1                                                             | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 2" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: Teachers should be able to import a series in the acl mode
    Given the following config values are set as admin:
      | config       | value | plugin         |
      | importmode_1 | acl   | block_opencast |
    When I click on "Go to overview..." "link"
    And I click on "Import videos" "button"
    And I click on "#import-course-1" "css_element"
    And I click on "Continue" "button"
    Then I should see "Test series"
    And I should see "Test video"
    When I click on "Import videos and return to overview" "button"
    Then I should see "The import of the selected series with its videos into this course was successful"

  @javascript
  Scenario: Teachers should be able to select and import a series in the acl mode
    Given the following config values are set as admin:
      | config       | value | plugin         |
      | importmode_1 | acl   | block_opencast |
    And I create a second series
    When I click on "Go to overview..." "link"
    And I click on "Import videos" "button"
    And I click on "#import-course-1" "css_element"
    And I click on "Continue" "button"
    Then I should see "Test series"
    And I should see "Another series"
    When I click on "#id_series_1111-1111-1111-1111-1111" "css_element"
    And I click on "Continue" "button"
    Then I should see "Another series"
    And I should see "My video"
    When I click on "Import videos and return to overview" "button"
    Then I should see "The import of the selected series with its videos into this course was successful"

  @javascript
  Scenario: Teachers should be able to import a series in the duplicate mode
    Given the following config values are set as admin:
      | config              | value           | plugin         |
      | importmode_1        | duplication     | block_opencast |
      | duplicateworkflow_1 | duplicate-event | block_opencast |
    When I click on "Go to overview..." "link"
    And I click on "Import videos" "button"
    And I click on "#import-course-1" "css_element"
    And I click on "Continue" "button"
    And I click on "Continue" "button"
    And I should see "Test video"
    When I click on "Import videos and return to overview" "button"
    Then I should see "The import of the selected videos into this course was scheduled"
    And I run all adhoc tasks
