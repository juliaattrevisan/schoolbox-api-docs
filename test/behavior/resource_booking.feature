Feature: Resource Booking Builder
  In order to track the usage of school property
  As the inventory manager
  I need to be able to create bookings for resources

@resource-administration
Scenario: Set a resource administrator
  Given I wipe the slate clean
  And   I go to homepage
  And   I log in as "alaress" with password "alaress"
  # Different permission for resource management service:
  # Read, Write: currently do nothing
  # Moderate: creates resource items and categories, can confirm bookings
  When  I fill in "search-field" with "Walter White"
  And   I press "search-button"
  And   I follow "Modify User"
  # Give "Moderate" permission to Walter
  And   I select "Resource Booking" from "serviceSelect"
  And   I check "Moderate"
  And   I press "serviceSubmit"
  Then  I should see "Resource Booking" in the "#permissions-list" element
  And   the "Moderate" checkbox should be checked

@resource-management
Scenario: Manage resource categories
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "wwhite" with password "password"
  # Will there be a "Bookings" link?
  When  I go to "/resource-booking/category"
  # Create some categories
  And   I follow "resourcecategory-create"
  And   I fill in "name" with "Boox"
  And   I press "resourcecategory-save"
  Then  I should see "Boox" in the ".resourcecategory-list" element
  When  I follow "resourcecategory-create"
  And   I fill in "name" with "Projectors"
  And   I press "resourcecategory-save"
  And   I follow "resourcecategory-create"
  And   I fill in "name" with "To be deleted"
  And   I press "resourcecategory-save"
  Then  I should see "Boox" in the ".resourcecategory-list" element
  And   I should see "Projectors" in the ".resourcecategory-list" element
  And   I should see "To be deleted" in the ".resourcecategory-list" element
  # Update one
  When  I follow "Boox"
  And   I fill in "name" with "Textbooks"
  And   I press "resourcecategory-save"
  Then  I should not see "Boox" in the ".resourcecategory-list" element
  And   I should see "Textbooks" in the ".resourcecategory-list" element
  And   I should see "Projectors" in the ".resourcecategory-list" element
  And   I should see "To be deleted" in the ".resourcecategory-list" element
  # Delete one
  When  I follow "To be deleted"
  And   I press "resourcecategory-delete"
  # There should be a confirm here, but currently there is not.
  Then  I should see "Textbooks" in the ".resourcecategory-list" element
  And   I should see "Projectors" in the ".resourcecategory-list" element
  And   I should not see "To be deleted" in the ".resourcecategory-list" element

@resource-management
Scenario: Manage resources
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "wwhite" with password "password"
  # Will there be a "Bookings" link? I guess so, because there are now resource moderators
  When  I go to "/resource-booking"
  # By default, this shows all categories
  # Create some resources
  And   I follow "create"
  And   I fill in the following:
    | field                       | value                                |
    | name                        | A single projector                   |
    | description                 | A projector that can be moved around |
    | resource_category_id        | Projectors                           |
    | resource_moderation_type_id | Open                                 |
    | min_lead_time               | 00:00:00                             |
    | min_reservation_length      | 01:00:00                             |
    | max_reservation_length      | 02:00:00                             |
  And  I press "save"
  Then I should see "A single projector" in the ".resource-list" element
  # Create a resource with a different moderator. (I can still moderate this resource.)
  When  I follow "create"
  And   I fill in the following:
    | field                       | value                       |
    | name                        | English textbook            |
    | description                 | A Unit 1/2 English textbook |
    | resource_category_id        | Textbooks                   |
    | resource_moderation_type_id | Moderated                   |
    | min_lead_time               | 00:00:00                    |
    | min_reservation_length      | 00:00:00                    |
    | max_reservation_length      | 168:00:00                   |
  # Entering a user into a autocomplete field
  And  I fill in autocomplete "moderator_id" with "Celia"
  And  I select "Celia Glenn" from autocomplete "moderator_id"
  And  I press "save"
  Then I should see "A single projector" in the ".resource-list" element
  And  I should see "English textbook" in the ".resource-list" element
  # Create a managed resource
  When  I follow "create"
  And   I fill in the following:
    | field                       | value                    |
    | name                        | The special projector    |
    | description                 | A projector made of gold |
    | resource_category_id        | Textbooks                |
    | resource_moderation_type_id | Moderated                |
    | min_lead_time               | 24:00:00                 |
    | min_reservation_length      | 01:00:00                 |
    | max_reservation_length      | 02:00:00                 |
  And  I fill in autocomplete "moderator_id" with "Jennifer"
  And  I select "Jennifer Shadbolt" from autocomplete "moderator_id"
  And  I fill in autocomplete "moderator_id" with "Lovejoy"
  And  I select "Peter Lovejoy" from autocomplete "moderator_id"
  And  I fill in autocomplete "moderator_id" with "Scott"
  And  I select "Scott Berryhill" from autocomplete "moderator_id"
  And  I press "Save"
  Then I should see "A single projector" in the ".resource-list" element
  And  I should see "English textbook" in the ".resource-list" element
  And  I should see "The special projector" in the ".resource-list" element
  # Update a resource
  When  I follow "The special projector"
  And   I fill in the following:
    | field                       | value                    |
    | name                        | The good projector       |
    | description                 | A projector made of gold |
    | resource_category_id        | Projectors               |
    | resource_moderation_type_id | Managed                  |
  And   I remove "Scott Berryhill" from autocomplete "moderator_id"
  And   I press "Save"
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  And   I should not see "The good projector" in the ".resource-list" element
  # Delete a resource
  When  I follow "create"
  And   I fill in the following:
    | field                       | value         |
    | name                        | To be deleted |
    | description                 | To be deleted |
    | resource_category_id        | Projectors    |
    | resource_moderation_type_id | Open          |
    | min_lead_time               | 00:00:00      |
    | min_reservation_length      | 01:00:00      |
    | max_reservation_length      | 02:00:00      |
  And   I press "Save"
  Then  I should see "To be deleted" in the ".resource-list" element
  And   I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  When  I follow "To be deleted"
  And   I press "Delete"
  # There should be a confirm here, but currently there is not.
  Then  I should not see "To be deleted" in the ".resource-list" element
  And   I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element

@resource-management
Scenario: Check resource permissions
  Given I do not wipe the slate clean
  And   I go to homepage
  # Regular users get a readonly view of resources, eventually they will be able
  # to book them
  And   I log in as "sberryhill" with password "password"
  When  I go to "/resource-booking"
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  # But I can't edit them, or confirm bookings for them
  When  I go to "/resource-booking/resource/1/edit"
  Then  the response status code should be 403
  # As someone who is the moderator of a single item:
  # Can I edit them? I'm not sure yet but this user doesn't have moderate perms.
  And   I log in as "cglenn" with password "password"
  When  I go to "/resource-booking"
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  When  I go to "/resource-booking/resource/1/edit"
  Then  the response status code should be 403

@resource-management
Scenario: Search for resources
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "sberryhill" with password "password"
  # Search thru global search bar
  When  I fill in "search-field" with "single projector"
  And   I press "search-button"
  Then  I should see "A single projector" in the ".information-list" element
  # Search thru dedicated resource search
  When  I go to "/resource-booking"
  # Filter by resource category selector
  And   I click "Projectors" in the ".resourcecategory-list" element
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should not see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  # Search thru resource search form
  When  I fill in "resource-search" with "good projector"
  And   I press Enter
  Then  I should not see "A single projector" in the ".resource-list" element
  And   I should not see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
