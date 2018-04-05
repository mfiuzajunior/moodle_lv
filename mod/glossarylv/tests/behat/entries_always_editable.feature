@mod @mod_glossarylv
Feature: A teacher can set whether glossarylv entries are always editable or not
  In order to ensure students think before adding new entries
  As a teacher
  I need to prevent entries to be always editable

  @javascript
  Scenario: Glossarylv entries are not always editable
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following config values are set as admin:
      | maxeditingtime | 60 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Glossarylv" to section "1" and I fill the form with:
      | Name | Test glossarylv name |
      | Description | Test glossarylv description |
      | Always allow editing | No |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test glossarylv name"
    When I add a glossarylv entry with the following data:
      | Concept | Test concept name |
      | Definition | Test concept description |
    Then "Delete entry: Test concept name" "link" should exist
    And "Edit entry: Test concept name" "link" should exist
    And I wait "65" seconds
    And I reload the page
    Then "Delete entry: Test concept name" "link" should not exist
    And "Edit entry: Test concept name" "link" should not exist
