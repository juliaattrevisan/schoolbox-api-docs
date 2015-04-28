Feature: admin-user-bulk-modify
  As a logged-in superuser
  I need to be able to bulk modify users

Scenario: Bulk modify users
  When  I wipe the slate clean
  When  log in as "alaress" with password "alaress"
  And   I go to "/adminv2/home"
  And   I follow "Users"
  Then  I should see "Bulk Modify"
  When  I follow "Bulk Modify"
  And   I wait for "Campus"
  Then  I should see "Role"
  And   I should see "ePortfolio"
  And   I should see "Year"
  When  I select "Junior Students" from "search_role"
  And   I press "Search Users"
  Then  I should not see "Found: 0"
  And   I should see "Selected: 0"
  When  I check "userId[]"
  When  I fill in "apply_year" with "38"
  And   I press "Apply To Selected"
  Then  I should see "1 users were processed."
  When  I follow "Back to Search"
  Then  I should see "Bulk Modify"
  When  I fill in "search_year" with "38"
  And   I press "Search Users"
  Then  I should see "Found: 1"
