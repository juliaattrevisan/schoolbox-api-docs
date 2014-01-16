Feature: admin-user
  As a logged-in superuser
  I need to be able to create users
  I need to be able to enabled specific options for users
  I need to be able to disable users
  I need to be able to delete users

Scenario: Create, login and modify a new user
  When  I wipe the slate clean
  Then  I go to homepage
  And   log in as "alaress" with password "alaress"
  When  I go to "/adminv2/home"
  And	I follow "Users"
  Then	I should see "Add User"
  When	I follow "Add User"
  Then	I should see "Create a user"
  When	I select "Prof" from "title"
  And	I fill in "firstname" with "Steve"
  And	I fill in "lastname" with "Test"
  And	I fill in "database" with "0"
  And	I select "Teachers" from "group"
  And	I check "campus[]"
  And	I check "defaultCampus"
  And	I fill in "username" with "stevetest"
  And	I fill in "password" with "password1"
  And	I fill in "alt_email" with "webmaster@alaress.com.au"
  And	I fill in "year" with ""
  And	I select "Fox" from "house"
  And	I select "Not Set" from "residential_house"
  And	I check "eportfolio"
  And	I uncheck "local_email"
  And	I uncheck "mail_handler"
  And	I check "webmail"
  And	I uncheck "superuser"
  And	I press "Add User"
  And	I check "account_enabled"
  Then	I should see "Modify user: Prof Steve Test"
  # need to enable account again due to SB-3743
  When	I check "account_enabled"
  And	I fill in "password" with "password2"
  And	I press "Update User"
  Then	I follow "Logout"
  And	log in as "stevetest" with password "password2"
  Then	I should see ", Prof Steve Test"
  And	I should see "Webmail"
  When	I follow "Prof Steve Test"
  Then	I should see "webmaster@alaress.com.au"
  And	I should not see "mailto:"
  And	I should not see "Student ID"
  And	I should not see "Grades"
  And	I should not see "Due Work"
  And	I should see "ePortfolio"

Scenario: Disable a user
  When	I follow "Logout"
  And	log in as "alaress" with password "alaress"
  And	I go to homepage
  And	I fill in "search-field" with "Prof Steve Test"
  And	I press "search-button"
  Then	I should see "webmaster@alaress.com.au"
  When	I follow "Modify User"
  Then	I should see "Modify user: Prof Steve Test"
  When	I uncheck "account_enabled"
  And	I press "Update User"
  Then	I should see "Account disabled"

Scenario: Delete a user
  When	I press "Delete User"
  Then  I should see "Modify User"
  And	I should see "Add User"
  When	I fill in "search-field" with "Prof Steve Test"
  And   I press "search-button"
  Then  I should not see "webmaster@alaress.com.au"
  When	I follow "Logout"
  And	log in as "stevetest" with password "password2"
  Then	I should see "The username or password you typed is incorrect, please try again"
