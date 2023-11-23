@block @block_opencast @block_opencast_autocomplete_metadata
Feature: Check and set autocompletion suggestions
  In order to get correct autocomplete suggestions
  As teacher
  I need to be able to view and modify metadata in add video, update metadata for event and series

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | Teacher   | 1        | teacher1@example.com | T1       |
      | teacher2 | Teacher   | 2        | teacher2@example.com | T2       |
      | manager1 | Manager   | 1        | manager1@example.com | M1       |
      | student1 | Student   | 1        | s1@example.com       | STD1     |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
      | manager1 | C1     | manager        |
    And I setup the default settigns for opencast plugins
    And the following config values are set as admin:
      | config              | value                                                                                      | plugin         |
      | apiurl_1            | http://testapi:8080                                                                        | tool_opencast  |
      | apipassword_1       | opencast                                                                                   | tool_opencast  |
      | apiusername_1       | admin                                                                                      | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}]                              | tool_opencast  |
      | limituploadjobs_1   | 0                                                                                          | block_opencast |
      | group_creation_1    | 0                                                                                          | block_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                                                   | block_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                                                   | block_opencast |
      | enablechunkupload_1 | 0                                                                                          | block_opencast |
      | workflow_roles_1    | republish-metadata                                                                         | block_opencast |
      | metadata_1          | [{"name":"creator","datatype":"autocomplete","required":0,"readonly":0,"param_json":null}] | block_opencast |
      | metadataseries_1    | [{"name":"creator","datatype":"autocomplete","required":0,"readonly":0,"param_json":null}] | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: Autocomplete suggestions for Presentors must be extracted from the access capabilities and only show teacher and editingteacher enroled users.
    When I click on "Go to overview..." "link"
    And I click on "#opencast-videos-table-1234-1234-1234-1234-1234_r0 .c3 .action-menu a" "css_element"
    And I click on "Update metadata" "link"
    Then I should see "Update metadata"
    When I expand the "Presenter(s)" autocomplete
    Then I should see "Teacher 1"
    And I should see "Teacher 2"
    And I should not see "Manager 1"
    And I click on "Teacher 1" item in the autocomplete list
    And I click on "Cancel" "button"
    When I click on "Add video" "button"
    Then I should see "Add video"
    And I click on "Show more..." "link"
    When I expand the "Presenter(s)" autocomplete
    Then I should see "Teacher 1"
    And I should see "Teacher 2"
    And I should not see "Manager 1"
    And I click on "Teacher 1" item in the autocomplete list
    And I click on "Cancel" "button"
    When I click on "Manage series" "link"
    And I click on "Create new series" "button"
    And I wait "2" seconds
    Then I should see "Create new series"
    When I expand the "Presenter(s)" autocomplete
    Then I should see "Teacher 1"
    And I should see "Teacher 2"
    And I should not see "Manager 1"
    And I click on "Teacher 1" item in the autocomplete list
    And I click on ".modal button.btn-secondary" "css_element"
    When I click on ".tabulator-row-odd i.fa-edit" "css_element"
    And I wait "2" seconds
    Then I should see "Edit series"
    When I expand the "Presenter(s)" autocomplete
    Then I should see "Teacher 1"
    And I should see "Teacher 2"
    And I should not see "Manager 1"
    And I click on "Teacher 1" item in the autocomplete list
    And I click on ".modal button.btn-secondary" "css_element"
