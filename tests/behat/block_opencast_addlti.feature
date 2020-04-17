@block @block_opencast
Feature: Add Opencast LTI module as Teacher
  In order to provide the uploaded videos to my students
  As teacher
  I need to be able to add an Opencast LTI module to my course

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
      | config          | value                    | plugin         |
      | apiurl          | 172.17.0.1:8080          | tool_opencast  |
      | apipassword     | opencast                 | tool_opencast  |
      | apiusername     | admin                    | tool_opencast  |
      | limituploadjobs | 0                        | block_opencast |
      | limitvideos     | 5                        | block_opencast |
      | group_creation  | 0                        | block_opencast |
      | group_name      | Moodle_course_[COURSEID] | block_opencast |
      | series_name     | Course_Series_[COURSEID] | block_opencast |
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > External tool > Manage tools" in site administration
    And I follow "Manage preconfigured tools"
    And I follow "Add preconfigured tool"
    And I set the following fields to these values:
      | Tool name                | Opencast series |
      | Tool URL                 | 172.17.0.1:8080/lti |
      | Custom parameters        | tool=ltitools/series/index.html |
      | Default launch container | Embed, without blocks |
      # The Opencast LTI provider does not need to be functional for this test. It just needs to be preconfigured in Moodle.
    And I press "Save changes"
    And I navigate to "Plugins > Blocks > Opencast Videos > Additional features" in site administration
    And I set the following fields to these values:
      | Enable ”Add LTI module"  | 1               |
      | Default LTI module title | Opencast videos |
      | Preconfigured LTI tool   | Opencast series |
    And I press "Save changes"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block
    And I log out

  Scenario: When the feature is enabled and working, editing teachers are able to add the LTI module to the course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    Then I should see "Provide videos"
    And I should see "Add Opencast LTI module to course"

  Scenario: When the feature is enabled and working, users who have been granted the right to view the recordings list but not to add the LTI module are not able to add the LTI module to the course
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher2 | Teacher   | 2        | teacher2@example.com | T2       |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
    And the following "permission overrides" exist:
      | capability                           | permission | role    | contextlevel | reference |
      | block/opencast:viewunpublishedvideos | Allow      | teacher | Course       | C1        |
    And I log in as "teacher2"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    Then I should not see "Provide videos"
    And I should not see "Add Opencast LTI module to course"

  Scenario: When the feature is disabled by the admin, editing teachers are not able to add the LTI module to the course
    Given the following config values are set as admin:
      | addltienabled | 0 | block_opencast |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    Then I should not see "Provide videos"
    And I should not see "Add Opencast LTI module to course"

  @javascript
  Scenario: After adding the LTI module to the course, the teacher sees to link to the LTI module in the Opencast overview.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    And I click on "Add Opencast LTI module to course" "button"
    And I should see "Opencast LTI module title"
    And I click on "Add Opencast LTI module to course" "button"
    Then I should see "Course 1" in the "#page-header" "css_element"
    And I should see "The 'Opencast videos' LTI module has been added to this course."
    And I should see "Opencast videos" in the "li.activity" "css_element"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Opencast videos" actions menu
    And I choose "Edit settings" in the open action menu
    Then the field "Preconfigured tool" matches value "Opencast series"
    # Now, the field "Custom parameters" should also contain the Opencast series ID, but we can't test that with Behat
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    Then I should see "Provide videos"
    And I should see "View Opencast LTI module in course"
    And I click on "View Opencast LTI module in course" "button"
    Then I should see "Opencast videos" in the "region-main" "region"

  Scenario: After adding the LTI module to the course, the teacher deletes the module manually and is able to add the module again in the Opencast overview.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    And I click on "Add Opencast LTI module to course" "button"
    And I should see "Opencast LTI module title"
    And I click on "Add Opencast LTI module to course" "button"
    And I should see "Course 1" in the "#page-header" "css_element"
    And I am on "Course 1" course homepage with editing mode on
    And I delete "Opencast videos" activity
    And I click on "Go to overview..." "link"
    Then I should see "Provide videos"
    And I should see "Add Opencast LTI module to course"

  Scenario: The admin is able to change the default title for the LTI module.
    Given the following config values are set as admin:
      | addltidefaulttitle | Sensational videos | block_opencast |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    And I click on "Add Opencast LTI module to course" "button"
    Then the field "Opencast LTI module title" matches value "Sensational videos"
    And I click on "Add Opencast LTI module to course" "button"
    Then I should see "Course 1" in the "#page-header" "css_element"
    And I should see "The 'Sensational videos' LTI module has been added to this course."
    And I should see "Sensational videos" in the "li.activity" "css_element"

  Scenario: The teacher is able to use a different title than the default title for the LTI module.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    And I click on "Add Opencast LTI module to course" "button"
    And the field "Opencast LTI module title" matches value "Opencast videos"
    And I set the following fields to these values:
      | Opencast LTI module title | Sensational videos |
    And I click on "Add Opencast LTI module to course" "button"
    Then I should see "Course 1" in the "#page-header" "css_element"
    And I should see "The 'Sensational videos' LTI module has been added to this course."
    And I should see "Sensational videos" in the "li.activity" "css_element"

  Scenario: The teacher is not allowed to use an empty title for the LTI module.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    And I click on "Add Opencast LTI module to course" "button"
    And I set the following fields to these values:
      | Opencast LTI module title | |
    And I click on "Add Opencast LTI module to course" "button"
    Then I should not see "Course 1" in the "#page-header" "css_element"
    And I should see "You have to set a title for the Opencast LTI module or to use the default title"

  Scenario: When the LTI tool is deleted by the admin, editing teachers are not able to add the LTI module to the course anymore
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > External tool > Manage tools" in site administration
    And I follow "Manage preconfigured tools"
    And I click on "#lti_configured_tools_container a.editing_delete" "css_element"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Go to overview..." "link"
    Then I should not see "Provide videos"
    And I should not see "Add Opencast LTI module to course"

  Scenario: When the LTI tool is deleted by the admin, the plugin configuration does not allow to set a tool anymore
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > External tool > Manage tools" in site administration
    And I follow "Manage preconfigured tools"
    And I click on "#lti_configured_tools_container a.editing_delete" "css_element"
    And I navigate to "Plugins > Blocks > Opencast Videos > Additional features" in site administration
    Then I should see "No preconfigured LTI tools to be used found. Please create an Opencast LTI tool first"
