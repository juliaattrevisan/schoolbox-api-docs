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
  When  I go to "/resource-booking/item"
  # By default, this shows all categories
  # Create some resources
  And   I follow "resource-create"
  And   I fill in the following:
    | name                 | A single projector                   |
    | description          | A projector that can be moved around |
    | min_lead_time        | 00:00:00                             |
    | min_reservation_time | 01:00:00                             |
    | max_reservation_time | 02:00:00                             |
  And  I select "Projectors" from "resource_category_id"
  And  I select "Open" from "resource_moderation_type_id"
  And  I press "resource-save"
  Then I should see "A single projector" in the ".resource-list" element
  # Create a resource with a different moderator. (I can still moderate this resource.)
  When  I follow "resource-create"
  And   I fill in the following:
    | name                 | English textbook            |
    | description          | A Unit 1/2 English textbook |
    | min_lead_time        | 00:00:00                    |
    | min_reservation_time | 00:00:00                    |
    | max_reservation_time | 168:00:00                   |
  And  I select "Textbooks" from "resource_category_id"
  And  I select "Moderated" from "resource_moderation_type_id"
  # Entering a user into a autocomplete field
  And  I fill in autocomplete "moderator_id" with "Celia"
  And  I select "Celia Glenn" from autocomplete "moderator_id"
  And  I press "resource-save"
  Then I should see "A single projector" in the ".resource-list" element
  And  I should see "English textbook" in the ".resource-list" element
  # Create a managed resource
  When  I follow "resource-create"
  And   I fill in the following:
    | name                 | The special projector    |
    | description          | A projector made of gold |
    | min_lead_time        | 24:00:00                 |
    | min_reservation_time | 01:00:00                 |
    | max_reservation_time | 02:00:00                 |
  And  I select "Textbooks" from "resource_category_id"
  And  I select "Moderated" from "resource_moderation_type_id"
  And  I fill in autocomplete "moderator_id" with "Jenifer"
  And  I select "Jenifer Shadbolt" from autocomplete "moderator_id"
  And  I fill in autocomplete "moderator_id" with "Lovejoy"
  And  I select "Peter Lovejoy" from autocomplete "moderator_id"
  And  I fill in autocomplete "moderator_id" with "Scott"
  And  I select "Scott Berryhill" from autocomplete "moderator_id"
  And  I press "resource-save"
  Then I should see "A single projector" in the ".resource-list" element
  And  I should see "English textbook" in the ".resource-list" element
  And  I should see "The special projector" in the ".resource-list" element
  # Update a resource
  When  I follow "The special projector"
  And   I fill in the following:
    | name        | The good projector       |
    | description | A projector made of gold |
  And   I select "Projectors" from "resource_category_id"
  And   I select "Managed" from "resource_moderation_type_id"
  And   I remove "Scott Berryhill" from autocomplete "moderator_id"
  And   I press "resource-save"
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  And   I should not see "The special projector" in the ".resource-list" element
  # Check its users
  When  I follow "The good projector"
  Then  the autocomplete "moderator_id" should not contain "Scott Berryhill"
  And   the autocomplete "moderator_id" should contain "Jenifer Shadbolt"
  And   the autocomplete "moderator_id" should contain "Peter Lovejoy"
  # Delete a resource
  When  I move backward one page
  And   I follow "resource-create"
  And   I fill in the following:
    | name                 | To be deleted |
    | description          | To be deleted |
    | min_lead_time        | 00:00:00      |
    | min_reservation_time | 01:00:00      |
    | max_reservation_time | 02:00:00      |
  And   I select "Projectors" from "resource_category_id"
  And   I select "Open" from "resource_moderation_type_id"
  And   I press "resource-save"
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

@resource-permissions
Scenario: Check resource permissions
  Given I do not wipe the slate clean
  And   I go to homepage
  # Regular users get a readonly view of resources, eventually they will be able
  # to book them
  And   I log in as "sberryhill" with password "password"
  When  I go to "/resource-booking/item"
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  # But I can't edit them, or confirm bookings for them
  When  I go to "/resource-booking/item/1/edit"
  # Selenium driver doesn't know how to get the status code.
  # Then  the response status code should be 403
  Then  I should see "Sorry, we are unable to complete this request"
  And   I should see "You do not have permission"
  # As someone who is the moderator of a single item:
  # Can I edit them? I'm not sure yet but this user doesn't have moderate perms.
  When  I log out
  And   I log in as "cglenn" with password "password"
  When  I go to "/resource-booking/item"
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  When  I go to "/resource-booking/item/1/edit"
  # Then  the response status code should be 403
  Then  I should see "Sorry, we are unable to complete this request"
  And   I should see "You do not have permission"

@resource-search
Scenario: Search for resources
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "sberryhill" with password "password"
  # Search thru global search bar
  When  I fill in "search-field" with "single projector"
  And   I press "search-button"
  Then  I should see "A single projector" in the ".information-list" element
  # Search thru dedicated resource search
  When  I go to "/resource-booking/item"
  # Filter by resource category selector
  And   I follow "Projectors"
  Then  I should see "A single projector"
  And   I should not see "English textbook"
  And   I should see "The good projector"
  # Search thru resource search form
  When  I fill in "resource-search-term" with "good projector"
  And   I press "resource-search"
  Then  I should not see "A single projector"
  And   I should not see "English textbook"
  And   I should see "The good projector"
