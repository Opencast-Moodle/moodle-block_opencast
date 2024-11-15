@block @block_opencast @block_opencast_massactions
Feature: Select all videos and perform mass actions in the Opencast Block Overview page
  As a teacher, I want to be able to select all videos from the video list table
  and perform mass actions so that I can manage multiple videos efficiently.
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
      | config              | value                                                                                   | plugin         |
      | apiurl_1            | http://testapi:8080                                                                     | tool_opencast  |
      | apipassword_1       | opencast                                                                                | tool_opencast  |
      | apiusername_1       | admin                                                                                   | tool_opencast  |
      | ocinstances         | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}]                           | tool_opencast  |
      | limituploadjobs_1   | 0                                                                                       | block_opencast |
      | group_creation_1    | 0                                                                                       | block_opencast |
      | group_name_1        | Moodle_course_[COURSEID]                                                                | block_opencast |
      | series_name_1       | Course_Series_[COURSEID]                                                                | block_opencast |
      | enablechunkupload_1 | 0                                                                                       | block_opencast |
      | workflow_roles_1    | republish-metadata                                                                      | block_opencast |
      | aclcontrolafter_1   | 1                                                                                       | block_opencast |
      | metadata_1          | [{"name":"rightsHolder","datatype":"text","required":0,"readonly":0,"param_json":null}] | block_opencast |
      | workflow_tags_1     | archive                                                                                 | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block

  @javascript
  Scenario: Teachers should see the mass action elements and be able to select and deselect all or one video, select actions from dropdown only when videos are selected, see the confrimation dialog when selecting a mass action and the video selection should be reset when the confirmation dialogue is canceled.
    Given I click on "Go to overview..." "link"
    Then the "With Selected Videos..." "select" should be disabled
    And the "Select all" checkbox should not be checked
    # Testing basic video selection via select all.
    When I click on "Select all" "checkbox"
    Then the "With Selected Videos..." "select" should be enabled
    And the "Select Test video" checkbox should be checked
    When I click on "Select all" "checkbox"
    Then the "With Selected Videos..." "select" should be disabled
    And the "Select Test video" checkbox should not be checked
    # Testing basic video selection via a video.
    When I click on "Select Test video" "checkbox"
    Then the "With Selected Videos..." "select" should be enabled
    And the "Select all" checkbox should be checked
    When I click on "Select Test video" "checkbox"
    Then the "With Selected Videos..." "select" should be disabled
    And the "Select all" checkbox should not be checked
    # Testing actions dropdowns selection.
    When I click on "Select all" "checkbox"
    Then the "With Selected Videos..." "select" should be enabled
    And the "Select Test video" checkbox should be checked
    When I click on "With Selected Videos..." "select"
    Then I should see "Delete"
    And I should see "Update metadata"
    And I should see "Change Visibility"
    And I should see "Start workflow"
    # Testing confirmation dialogue and reset video selection when confirmation is cancelled.
    When I select "Update metadata" from the "With Selected Videos..." singleselect
    Then I should see "Are you sure you want to update the metadata of the following selected videos:"
    When I click on "Cancel" "button" in the "Update metadata of selected videos" "dialogue"
    Then the "Select all" checkbox should not be checked
    And the "Select Test video" checkbox should not be checked
    And the "With Selected Videos..." "select" should be disabled

  @javascript
  Scenario: The mass actions should not be provided when conditions are not met, such as permissions and configurations.
    Given the following "permission overrides" exist:
      | capability                    | permission  | role           | contextlevel | reference |
      | block/opencast:deleteevent    | Prevent     | editingteacher | Course       | C1        |
      | block/opencast:startworkflow  | Prevent     | editingteacher | Course       | C1        |
      | block/opencast:addvideo       | Prevent     | editingteacher | Course       | C1        |
    When I click on "Go to overview..." "link"
    Then I should not see "With Selected Videos..."
    When the following "permission overrides" exist:
      | capability                    | permission  | role           | contextlevel | reference |
      | block/opencast:deleteevent    | Allow       | editingteacher | Course       | C1        |
      | block/opencast:startworkflow  | Allow       | editingteacher | Course       | C1        |
      | block/opencast:addvideo       | Allow       | editingteacher | Course       | C1        |
    And I reload the page
    Then I should see "With Selected Videos..."
    When the following config values are set as admin:
      | workflow_tags_1   |   | block_opencast |
      | aclcontrolafter_1 | 0 | block_opencast |
    And I reload the page
    And I click on "With Selected Videos..." "select"
    Then "Start workflow" "option" should not exist in the "With Selected Videos..." "select"
    And "Change Visibility" "option" should not exist in the "With Selected Videos..." "select"
    When the following config values are set as admin:
      | workflow_tags_1   | archive | block_opencast |
      | aclcontrolafter_1 | 1 | block_opencast |
    And I reload the page
    And I click on "With Selected Videos..." "select"
    Then I should see "Delete"
    And I should see "Update metadata"
    And I should see "Change Visibility"
    And I should see "Start workflow"

  @javascript
  Scenario: Teachers should be able to perform the mass action after the confirmation
    Given I click on "Go to overview..." "link"
    # Testing start workflow.
    When I click on "Select all" "checkbox"
    And I select "Start workflow" from the "With Selected Videos..." singleselect
    Then I should see "You have selected the following videos to start workflow for:"
    And I wait "1" seconds
    And I set the field "workflow" to "duplicate-event"
    When I click on "Start workflow" "button"
    And I wait "2" seconds
    Then I should see "Workflow has been successfully started for the selected videos:"
    # Testing Change Visibility.
    When I click on "Select all" "checkbox"
    And I select "Change Visibility" from the "With Selected Videos..." singleselect
    Then I should see "Are you sure you want to perform visibility change on the following selected videos:"
    When I click on "Change Visibility" "button"
    And I wait "1" seconds
    Then I should see "You have selected the following video(s):"
    And I click on "#id_visibility_1" "css_element"
    And I click on "Save changes" "button"
    And I should see "The visibility of the selected video(s) has been successfully updated:"
    # Testing Update metadata.
    When I click on "Select all" "checkbox"
    And I select "Update metadata" from the "With Selected Videos..." singleselect
    Then I should see "Are you sure you want to update the metadata of the following selected videos:"
    When I click on "Update metadata" "button"
    And I wait "1" seconds
    Then I should see "You have selected the following video(s):"
    And I click on "#id_rightsHolder_enabled" "css_element"
    And I set the field "Rights" to "TEST TEXT FOR RIGHTS"
    When I click on "Save changes" "button"
    And I wait "1" seconds
    Then I should see "The metadata of the selected video(s) has been successfully updated:"
    # Testing Delete.
    When I click on "Select all" "checkbox"
    And I select "Delete" from the "With Selected Videos..." singleselect
    Then I should see "Are you sure you want to delete the following selected videos:"
    When I click on "Delete" "button" in the ".modal" "css_element"
    And I wait "1" seconds
    Then I should see "The following selected video will be deleted shortly:"
