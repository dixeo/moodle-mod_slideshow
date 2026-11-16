@mod @mod_slideshow @javascript
Feature: Slideshow web service calls survive a lost connection
  In order to keep editing slides through short network losses
  As a user
  I need slideshow web service failures to be classified and retried safely

  Scenario: The slideshow network module classifies failures and never resends a call
    Given I log in as "admin"
    When I am on fixture page "/mod/slideshow/tests/behat/fixtures/network_testpage.php"
    And I wait until "DONE" "text" exists
    Then I should see "PASS h"
    And I should see "PASS i"
    And I should not see "FAIL"
