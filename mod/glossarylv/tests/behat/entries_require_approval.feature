@mod @mod_glossarylv
Feature: A teacher can choose whether glossarylv entries require approval
  In order to check entries before they are displayed
  As a user
  I need to enable entries requiring approval

  Scenario: Approve and undo approve glossarylv entries
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    Given I add a "Glossarylv" to section "1" and I fill the form with:
      | Name | Test glossarylv name |
      | Description | Test glossarylv entries require approval |
      | Approved by default | No |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test glossarylv name"
    When I add a glossarylv entry with the following data:
      | Concept | Just a test concept |
      | Definition | Concept definition |
      | Keyword(s) | Black |
    And I log out
    # Test that students can not see the unapproved entry.
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test glossarylv name"
    Then I should see "No entries found in this section"
    And I log out
    # Approve the entry.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test glossarylv name"
    And I follow "Waiting approval"
    Then I should see "(this entry is currently hidden)"
    And I follow "Approve"
    And I follow "Test glossarylv name"
    Then I should see "Concept definition"
    And I log out
    # Check that the entry can now be viewed by students.
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test glossarylv name"
    Then I should see "Concept definition"
    And I log out
    # Undo the approval of the previous entry.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test glossarylv name"
    And I follow "Undo approval"
    And I log out
    # Check that the entry is no longer visible by students.
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test glossarylv name"
    Then I should see "No entries found in this section"
