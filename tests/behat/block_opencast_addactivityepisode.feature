@block @block_opencast
Feature: Add Opencast Video Provider episode module as Teacher
  In order to provide the uploaded videos to my students
  As teacher
  I need to be able to add an Opencast episode module to my course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | Teacher   | 1        | teacher1@example.com | T1       |
    And the following "courses" exist:
      | fullname | shortname | format | category | id  |
      | Course 1 | C1        | topics | 0        | 123 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following config values are set as admin:
      | config                           | value                                                         | plugin         |
      | apiurl                           | http://testapi:8080                                           | tool_opencast  |
      | apipassword                      | opencast                                                      | tool_opencast  |
      | apiusername                      | admin                                                         | tool_opencast  |
      | ocinstances                      | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1                | 0                                                             | block_opencast |
      | limitvideos_1                    | 5                                                             | block_opencast |
      | group_creation_1                 | 0                                                             | block_opencast |
      | group_name_1                     | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1                    | Course_Series_[COURSEID]                                      | block_opencast |
      | addactivityepisodeenabled_1      | 1                                                             | block_opencast |
      | addactivityepisodeintro_1        | 1                                                             | block_opencast |
      | addactivityepisodesection_1      | 1                                                             | block_opencast |
      | addactivityepisodeavailability_1 | 1                                                             | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block
    And I log out

  Scenario: When the feature is enabled and working, editing teachers are able to add the LTI module to the course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    And I click on "Add Opencast episode module to course" "link"
    And I click on "Add module and return to course" "button"
    And I should see "The 'Test video' episode module has been added to this course."
    And I should see "Test video" in the "li.activity" "css_element"
    When I click on "Go to overview..." "link"
    Then "View Opencast episode module in course" "link" should exist
    And "Add Opencast episode module to course" "link" should not exist
