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
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |
      # Role Manager is required for teachers to be able to import series into a course.
      | teacher1 | C1     | manager        |
    And I setup the default settigns for opencast plugins
    And the following config values are set as admin:
      | config              | value                                                         | plugin         |
      | apiurl_1            | http://testapi:8080                                           | tool_opencast  |
      | apipassword_1       | opencast                                                      | tool_opencast  |
      | apiusername_1       | admin                                                         | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1   | 0                                                             | tool_opencast |
      | group_creation_1    | 0                                                             | tool_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                      | tool_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                      | tool_opencast |
      | enablechunkupload_1 | 0                                                             | tool_opencast |
      | workflow_roles_1    | republish-metadata                                            | tool_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: Teachers should be able to create a new series
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should see "Test series"
    When I click on "Create new series" "button"
    Then I should see "Title"
    Then ".modal i.icon[title='Required']" "css_element" should be visible
    When I set the field "Title" to "My new series"
    And I set the field "Rights" to "Some user"
    And I select "ALLRIGHTS" from the "License" singleselect
    And I click on "Create new series" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "A new series has been successfully created."

  @javascript
  Scenario: Teachers should be able to edit an existing series
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should see "Test series"
    When I click on ".tabulator-row-odd i.fa-edit" "css_element"
    Then I should see "Title"
    When I set the field "Title" to "Another series title"
    And I set the field "Rights" to "Some user"
    And I select "ALLRIGHTS" from the "License" singleselect
    And I click on "Edit series" "button"
    And I wait "1" seconds
    Then I should see "The series has been successfully updated."

  @javascript
  Scenario: Teachers should not be able to create/import series if the maximum number of series is reached
    Given the following config values are set as admin:
      | config      | value | plugin         |
      | maxseries_1 | 1     | tool_opencast |
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should not see "Create new series"
    And I should not see "Import series"

  @javascript
  Scenario: Teachers should be able to select a different default series
    Given I create a second series
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on ".tabulator-row-even input[name=\"defaultseries\"]" "css_element"
    Then I should see "Do you really want to use this series as new default series"
    When I click on "Save changes" "button"
    And I wait "1" seconds
    Then I should see "The default series has been successfully changed."

  @javascript
  Scenario: Teachers should be able to delete a series but should not be able to delete the default series
    Given I create a second series
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on ".tabulator-row-even i.fa-trash" "css_element"
    Then I should see "Are you sure you want to delete this series"
    When I click on "Delete" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "The series has been successfully deleted."
    When I click on ".tabulator-row-odd i.fa-trash" "css_element"
    Then I should see "Cannot Delete Default Series"
    When I click on "OK" "button" in the ".modal" "css_element"
    Then I should see "1234-1234-1234-1234-1234"

  @javascript
  Scenario: Teachers should be able to import a series but should not be able to import a series twice
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on "Import series" "button"
    And I set the field "Series ID" to "1111-1111-1111-1111-1111"
    And I click on "Import series" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "The series has been successfully imported."
    When I click on "Import series" "button"
    And I set the field "Series ID" to "1111-1111-1111-1111-1111"
    And I click on "Import series" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "The series you are trying to import is already present. Please choose a different series."

  @javascript
  Scenario: When manually deleting a block, teacher will be asked to decide whether to delete seriesmapping in a confirmation.
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    And I open the action menu in "Opencast Videos" "block"
    And I click on "Delete Opencast Videos block" "link"
    Then I should see "Remove Opencast Block?"
    When I click on "Delete block, but keep series mapping" "link"
    Then I add the "Opencast Videos" block
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should see "Test series"
    When I am on "Course 1" course homepage with editing mode on
    When I open the action menu in "Opencast Videos" "block"
    And I click on "Delete Opencast Videos block" "link"
    When I click on "Delete block and series mapping" "text"
    And I wait to be redirected
    Then I add the "Opencast Videos" block
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    Then I should see "No series is defined yet."

  @javascript
  Scenario: Import a series to a course is only possible when the teacher has the capability to do so in any of the mapped courses to a series
    Given the following config values are set as admin:
      | config      | value | plugin         |
      | maxseries_1 | 2     | tool_opencast |
    And I am on "Course 2" course homepage with editing mode on
    And I add the "Opencast Videos" block
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on "Import series" "button"
    And I set the field "Series ID" to "1111-1111-1111-1111-1111"
    When I click on "Import series" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "The series has been successfully imported."
    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on "Import series" "button"
    And I set the field "Series ID" to "1111-1111-1111-1111-1111"
    When I click on "Import series" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "Importing this series into this course is not allowed. Please select a different series or contact the system administrator for assistance."
    When I am on the "C2" "Course" page logged in as "admin"
    And I navigate to course participants
    And I click on "Teacher 1's role assignments" "link"
    And I type "Manager"
    And I press the enter key
    And I click on "Save changes" "link"
    Then I should see "Manager" in the "Teacher 1" "table_row"
    When I am on the "C2" "Course" page logged in as "teacher1"
    And I click on "Go to overview..." "link"
    And I click on "Manage series" "link"
    And I click on "Import series" "button"
    And I set the field "Series ID" to "1234-1234-1234-1234-1234"
    When I click on "Import series" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "The series has been successfully imported."
