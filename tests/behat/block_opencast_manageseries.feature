@block @block_opencast @block_opencast_manageseries
Feature: Manage series as Teacher
  In order to manage series for a course
  As teacher
  I need to be able to create, edit, import and delete series

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
      | apiurl              | http://testapi:8080                                           | tool_opencast  |
      | apipassword         | opencast                                                      | tool_opencast  |
      | apiusername         | admin                                                         | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1   | 0                                                             | block_opencast |
      | group_creation_1    | 0                                                             | block_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1 | 0                                                             | block_opencast |
      | workflow_roles_1    | republish-metadata                                            | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: Teachers should be able to create a new series
    When I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should see "Test series"
    When I click on "Create new series" "button"
    Then I should see "Subjects"
    When I click on "Create new series" "button" in the ".modal" "css_element"
    Then I should see "Required"
    When I set the field "Title" to "My new series"
    And I set the field "Rights" to "Rightsholder"
    And I set the field "License" to "All Rights Reserved"
    And I click on "Create new series" "button" in the ".modal" "css_element"
    And I wait "2" seconds
    Then I should see "My new series"
    And I should see "84bab8de-5688-46a1-9af0-5ce9122eeb6a"

  @javascript
  Scenario: Teachers should be able to edit an existing series
    When I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should see "Test series"
    When I click on ".tabulator-row-odd i.fa-edit" "css_element"
    Then I should see "Subjects"
    When I set the field "Title" to "Another series title"
    And I set the field "Rights" to "Rightsholder"
    And I set the field "License" to "All Rights Reserved"
    And I click on "Edit series" "button"
    And I wait "2" seconds
    Then I should not see "Updating the series metadata failed"
    And I should see "Another series title"

  @javascript
  Scenario: Teachers should not be able to create/import series if the maximum number of series is reached
    Given the following config values are set as admin:
      | config      | value | plugin         |
      | maxseries_1 | 1     | block_opencast |
    When I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should not see "Create new series"
    And I should not see "Import series"

  @javascript
  Scenario: Teachers should not be able to select a different default series
    Given I create a second series
    When I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on ".tabulator-row-even input[name=\"defaultseries\"]" "css_element"
    Then I should see "Do you really want to use this series as new default series"
    When I click on "Save changes" "button"
    And I wait "2" seconds
    Then I should not see "Changing the default series failed"

  @javascript
  Scenario: Teachers should not be able to delete the default series
    When I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on ".tabulator-row-odd i.fa-trash" "css_element"
    Then I should see "Are you sure you want to delete this series"
    When I click on "Delete" "button" in the ".modal" "css_element"
    And I wait "2" seconds
    Then I should see "You cannot delete the default series."

  @javascript
  Scenario: Teachers should be able to delete a non-default series
    Given I create a second series
    When I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on ".tabulator-row-even i.fa-trash" "css_element"
    Then I should see "Are you sure you want to delete this series"
    When I click on "Delete" "button" in the ".modal" "css_element"
    And I wait "2" seconds
    Then I should not see "Another series"

  @javascript
  Scenario: Teachers should be able to import a series
    When I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on "Import series" "button"
    And I set the field "Series ID" to "1111-1111-1111-1111-1111"
    And I click on "Import series" "button" in the ".modal" "css_element"
    And I wait "2" seconds
    Then I should not see "The series could not be imported"