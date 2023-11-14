@block @block_opencast @block_opencast_cleanup
Feature: Cleanup and update the lti modules
  In order to cleanup the manually created lti modules
  As teacher
  I need to be able to create LTI modules manually

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
      | config                             | value                                                         | plugin         |
      | apiurl_1                           | http://testapi:8080                                           | tool_opencast  |
      | apipassword_1                      | opencast                                                      | tool_opencast  |
      | apiusername_1                      | admin                                                         | tool_opencast  |
      | ocinstances                        | [{"id":1,"name":"Default","isvisible":true,"isdefault":true}] | tool_opencast  |
      | limituploadjobs_1                  | 0                                                             | block_opencast |
      | group_creation_1                   | 0                                                             | block_opencast |
      | group_name_1                       | Moodle_course_[COURSEID]                                      | block_opencast |
      | series_name_1                      | Course_Series_[COURSEID]                                      | block_opencast |
      | enablechunkupload_1                | 0                                                             | block_opencast |
      | workflow_roles_1                   | republish-metadata                                            | block_opencast |
      | importvideosenabled_1              | 1                                                             | block_opencast |
      | importvideosmanualenabled_1        | 1                                                             | block_opencast |
      | importmode_1                       | duplication                                                   | block_opencast |
      | duplicateworkflow_1                | duplicate-event                                               | block_opencast |
      | importvideoshandleseriesenabled_1  | 1                                                             | block_opencast |
      | importvideoshandleepisodeenabled_1 | 1                                                             | block_opencast |
      | addltiepisodeenabled_1             | 1                                                             | block_opencast |
    And I setup the opencast test api
    And I upload a testvideo
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > External tool > Manage tools" in site administration
    And I follow "Manage preconfigured tools"
    And I follow "Add preconfigured tool"
    And I set the following fields to these values:
      | Tool name                | Opencast series                 |
      | Tool URL                 | 172.17.0.1:8080/lti             |
      | Custom parameters        | tool=ltitools/series/index.html |
      | Default launch container | Embed, without blocks           |
    And I press "Save changes"
    And I follow "Add preconfigured tool"
    And I set the following fields to these values:
      | Tool name                | Opencast episode                |
      | Tool URL                 | 172.17.0.1:8080/lti             |
      | Custom parameters        | tool=ltitools/player/index.html |
      | Default launch container | Embed, without blocks           |
    And I press "Save changes"
    And I navigate to "Plugins > Blocks > Opencast Videos > LTI module features" in site administration
    And I set the following fields to these values:
      | Enable "Add LTI series module"             | 1                |
      | Default LTI series module title            | Opencast videos  |
      | Preconfigured LTI tool for series modules  | Opencast series  |
      | Enable "Add LTI episode module"            | 1                |
      | Preconfigured LTI tool for episode modules | Opencast episode |
    And I press "Save changes"

  @javascript
  Scenario: Teacher should be able to add LTI module manually and the record should be captured and cleaup must be executed when faulty entry recognized
    Given I am on "Course 1" course homepage with editing mode on
    And I add the "Opencast Videos" block
    And I add a "External tool" to section "1"
    And I set the field "Activity name" to "Opencast episode Manual Faulty"
    And I expand all fieldsets
    And I set the field "Preconfigured tool" to "Opencast episode"
    And I set the field "Custom parameters" to "id=abcd"
    And I press "Save and return to course"
    And I add a "External tool" to section "1"
    And I set the field "Activity name" to "Opencast episode Manual Correct"
    And I expand all fieldsets
    And I set the field "Preconfigured tool" to "Opencast episode"
    And I set the field "Custom parameters" to "id=abcd-abcd-abcd-abcd"
    And I press "Save and return to course"
    And I add a "External tool" to section "1"
    And I set the field "Activity name" to "Opencast series Manual Faulty"
    And I expand all fieldsets
    And I set the field "Preconfigured tool" to "Opencast series"
    And I set the field "Custom parameters" to "series=1234"
    And I press "Save and return to course"
    And I add a "External tool" to section "1"
    And I set the field "Activity name" to "Opencast series Manual Correct"
    And I expand all fieldsets
    And I set the field "Preconfigured tool" to "Opencast series"
    And I set the field "Custom parameters" to "series=1234-1234-1234-1234-1234"
    And I press "Save and return to course"
    Then I should see "Opencast episode Manual Faulty"
    And I should see "Opencast episode Manual Correct"
    And I should see "Opencast series Manual Correct"
    And I should see "Opencast series Manual Faulty"
    And I run the scheduled task "\block_opencast\task\cleanup_lti_module_cron"
    When I reload the page
    Then I should not see "Opencast episode Manual Faulty"
    And I should not see "Opencast series Manual Faulty"
    And I should see "Opencast episode Manual Correct"
    And I should see "Opencast series Manual Correct"
    When I open "Opencast episode Manual Correct" actions menu
    And I choose "Edit settings" in the open action menu
    Then I set the field "Activity name" to "Opencast episode Manual Correct Edited Faulty"
    And I expand all fieldsets
    And I set the field "Custom parameters" to "id=abcd"
    And I press "Save and return to course"
    When I open "Opencast series Manual Correct" actions menu
    And I choose "Edit settings" in the open action menu
    Then I set the field "Activity name" to "Opencast series Manual Correct Edited"
    And I expand all fieldsets
    And I set the field "Custom parameters" to "series=1234-1234-1234-1234-1234"
    When I press "Save and return to course"
    Then I should see "Opencast episode Manual Correct Edited Faulty"
    And I should see "Opencast series Manual Correct Edited"
    And I run the scheduled task "\block_opencast\task\cleanup_lti_module_cron"
    When I reload the page
    Then I should not see "Opencast episode Manual Correct Edited Faulty"
    And I should see "Opencast series Manual Correct Edited"
