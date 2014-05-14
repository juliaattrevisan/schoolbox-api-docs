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
  # Moderate: creates resource items and categories, can approve bookings
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
  # There should be a approve here, but currently there is not.
  Then  I should see "Textbooks" in the ".resourcecategory-list" element
  And   I should see "Projectors" in the ".resourcecategory-list" element
  And   I should not see "To be deleted" in the ".resourcecategory-list" element

@resource-management @resource-management-create
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
    | name                      | A single projector                   |
    | description               | A projector that can be moved around |
    | min_lead_time[day]        | 01                                   |
    | min_reservation_time[day] | 01                                   |
    | max_reservation_time[day] | 02                                   |
  And  I select "Projectors" from "resource_category_id"
  And  I select "Open" from "resource_moderation_type_id"
  And  I press "resource-save"
  Then I should see "A single projector" in the ".resource-list" element
  # Create a resource with a different moderator. (I can still moderate this resource.)
  When  I follow "resource-create"
  And   I fill in the following:
    | name                      | English textbook            |
    | description               | A Unit 1/2 English textbook |
    | max_reservation_time[day] | 168                         |
  And  I select "Textbooks" from "resource_category_id"
  And  I select "Moderated" from "resource_moderation_type_id"
  # Entering a user into a autocomplete field
  And  I fill in autocomplete "moderator_id[]" with "Celia"
  And  I select "Celia Glenn" from autocomplete "moderator_id[]"
  And  I press "resource-save"
  Then I should see "A single projector" in the ".resource-list" element
  And  I should see "English textbook" in the ".resource-list" element
  # Create a managed resource
  When  I follow "resource-create"
  And   I fill in the following:
    | name                      | The special projector    |
    | description               | A projector made of gold |
    | min_lead_time[day]        | 24                       |
    | min_reservation_time[day] | 01                       |
    | max_reservation_time[day] | 02                       |
  And  I select "Textbooks" from "resource_category_id"
  And  I select "Moderated" from "resource_moderation_type_id"
  And  I fill in autocomplete "moderator_id[]" with "Jenifer"
  And  I select "Jenifer Shadbolt" from autocomplete "moderator_id[]"
  And  I fill in autocomplete "moderator_id[]" with "Lovejoy"
  And  I select "Peter Lovejoy" from autocomplete "moderator_id[]"
  And  I fill in autocomplete "moderator_id[]" with "Scott"
  And  I select "Scott Berryhill" from autocomplete "moderator_id[]"
  And  I press "resource-save"
  Then I should see "A single projector" in the ".resource-list" element
  And  I should see "English textbook" in the ".resource-list" element
  And  I should see "The special projector" in the ".resource-list" element
  # Update a resource
  When  I edit resource "The special projector"
  And   I fill in the following:
    | name        | The good projector       |
    | description | A projector made of gold |
  And   I select "Projectors" from "resource_category_id"
  And   I select "Managed" from "resource_moderation_type_id"
  And   I remove "Scott Berryhill" from autocomplete "moderator_id[]"
  And   I press "resource-save"
  Then  I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  And   I should not see "The special projector" in the ".resource-list" element
  # Check its users
  When  I edit resource "The good projector"
  Then  the autocomplete "moderator_id[]" should not contain "Scott Berryhill"
  And   the autocomplete "moderator_id[]" should contain "Jenifer Shadbolt"
  And   the autocomplete "moderator_id[]" should contain "Peter Lovejoy"
  # Delete a resource
  When  I move backward one page
  And   I follow "resource-create"
  And   I fill in the following:
    | name                      | To be deleted |
    | description               | To be deleted |
    | min_lead_time[day]        | 00            |
    | min_reservation_time[day] | 01            |
    | max_reservation_time[day] | 02            |
  And   I select "Projectors" from "resource_category_id"
  And   I select "Open" from "resource_moderation_type_id"
  And   I press "resource-save"
  Then  I should see "To be deleted" in the ".resource-list" element
  And   I should see "A single projector" in the ".resource-list" element
  And   I should see "English textbook" in the ".resource-list" element
  And   I should see "The good projector" in the ".resource-list" element
  When  I edit resource "To be deleted"
  And   I press "resource-delete"
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
  # But I can't edit them, or approve bookings for them
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
Scenario: Search for resources (from the global search)
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  # Search thru global search bar - not sure if required
  When  I fill in "search-field" with "single projector"
  And   I press "search-button"
  Then  I should see "A single projector" in the ".information-list" element

@resource-search
Scenario: Search for resources (from the resource module)
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
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

@resource-relations
Scenario: Adding related resources to a resource
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "wwhite" with password "password"
  When  I go to "/resource-booking/item"
  And   I follow "resource-create"
  And   I fill in the following:
    | name                      | Book box                       |
    | description               | A box for the English textbook |
    | max_reservation_time[day] | 168                            |
  And   I select "Textbooks" from "resource_category_id"
  And   I fill in autocomplete "related_resource_id[]" with "English"
  And   I select "English textbook" from autocomplete "related_resource_id[]"
  And   I fill in autocomplete "related_resource_id[]" with "projector"
  And   I select "A single projector" from autocomplete "related_resource_id[]"
  And   I press "resource-save"
  # Check the related resources
  When  I edit resource "Book box"
  Then  the autocomplete "related_resource_id[]" should contain "English textbook"
  And   the autocomplete "related_resource_id[]" should contain "A single projector"
  # Relations should be reciprocal too
  When  I move backward one page
  And   I edit resource "English textbook"
  Then  the autocomplete "related_resource_id[]" should contain "Book box"
  And   the autocomplete "related_resource_id[]" should not contain "A single projector"
  # And reciprocally deleted
  When  I move backward one page
  And   I edit resource "A single projector"
  And   I remove "Book box" from autocomplete "related_resource_id[]"
  And   I press "resource-save"
  And   I edit resource "A single projector"
  Then  I should not see "Book box"
  When  I move backward one page
  And   I edit resource "English textbook"
  Then  the autocomplete "related_resource_id[]" should contain "Book box"
  And   the autocomplete "related_resource_id[]" should not contain "A single projector"
  When  I move backward one page
  And   I edit resource "Book box"
  Then  the autocomplete "related_resource_id[]" should contain "English textbook"
  And   the autocomplete "related_resource_id[]" should not contain "A single projector"

@resource-booking @resource-booking-open
Scenario: Booking an open resource
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  # "A single projector":
  # Anyone can book this resource.
  # Lead time: 1 day
  # Min reservation time: 1 day
  # Max reservation time: 2 days
  # All standard rules regarding bookings apply.
  When  I go to "/resource-booking/item"
  # Current link: "Book" next to resource name
  And   I view details for resource "A single projector"
  # Book a resource: test against its min and max reserve time, and its lead time
  And   I start booking resource for "+2 days"
  Then  the datepicker "start_datetime[date]" should contain "+2 days"
  And   the datepicker "end_datetime[date]" should contain "+3 days"
  # Make the booking for 2:00pm tomorrow, for 1 day
  When  I fill in "start_datetime[hour]" with "2"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "PM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "2"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "PM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a "#calendar" element
  Then  I should see approved booking at "2:00pm +2 days"

@resource-booking @resource-booking-moderated
Scenario: Booking a moderated resource
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "English textbook"
  And   I start booking resource for "+1 days"
  When  I fill in "start_datetime[hour]" with "2"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "PM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "2"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "PM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a "#calendar" element
  Then  I should see pending booking at "2:00pm +1 days"
  And   I should not be able to approve booking at "2:00pm +1 days"
  # Log in as someone who can approve and reject this booking
  Given I log out
  And   I log in as "cglenn" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "English textbook"
  Then  I should see pending booking at "2:00pm +1 days"
  # In order to see rejected items, I need to enable them in the filter
  When  I click "#fc-button-filter"
  And   I check "Rejected"
  And   I click "#applyFilters"
  And   I wait for AJAX to finish
  And   I reject booking at "2:00pm +1 days"
  And   I wait for AJAX to finish
  Then  I should see rejected booking at "2:00pm +1 days"
  # I changed my mind, approve it after all
  When  I approve booking at "2:00pm +1 days"
  And   I wait for AJAX to finish
  Then  I should see approved booking at "2:00pm +1 days"

#Scenario: Making a recurring booking for a resource

#Scenario: Booking a resource for someone else (if I have moderator perms on item)

@resource-booking @resource-booking-managed
Scenario: Booking a managed resource
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "The good projector"
  Then  I should not be able to book resource
  # Log in as someone who can book this resource
  Given I log out
  And   I log in as "wwhite" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "The good projector"
  # "The good projector":
  # Anyone can book this resource.
  # Lead time: 24 days
  # Min reservation time: 1 day
  # Max reservation time: 2 days
  And   I start booking resource for "+30 days"
  When  I fill in "start_datetime[hour]" with "2"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "PM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "2"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "PM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a "#calendar" element
  Then  I should see pending booking at "2:00pm +30 days"
  When  I approve booking at "2:00pm +30 days"
  And   I wait for AJAX to finish
  Then  I should see approved booking at "2:00pm +30 days"

@resource-booking
Scenario: Cancelling a booking
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "A single projector"
  And   I start booking resource for "+6 days"
  When  I fill in "start_datetime[hour]" with "3"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "PM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "3"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "PM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a "#calendar" element
  Then  I should see approved booking at "3pm +6 days"
  # And cancel it
  When  I cancel booking at "3pm +6 days"
  And   I wait for AJAX to finish
  Then  I should not see approved booking at "3pm +6 days"

@resource-booking @resource-booking-errors @resource-booking-errors-conflict
Scenario: Double-booking an item
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "A single projector"
  # Create a booking for this item for others to conflict with
  And   I start booking resource for "+30 days"
  When  I fill in datepicker "start_datetime[date]" with "+30 days"
  And   I fill in datepicker "end_datetime[date]" with "+31 days"
  And   I fill in "start_datetime[hour]" with "12"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "AM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "12"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "AM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a "#calendar" element
  # There's already a booking for +30 days 12AM - +31 days 12AM
  # Try creating one that starts at +30 days 11PM
  When  I go to "/resource-booking/item"
  And   I view details for resource "A single projector"
  And   I start booking resource for "+30 days"
  When  I fill in datepicker "start_datetime[date]" with "+30 days"
  And   I fill in datepicker "end_datetime[date]" with "+31 days"
  And   I fill in "start_datetime[hour]" with "11"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "PM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "11"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "PM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a ".error" element
  Then  I should see "This item has already been booked for this time" in the "label.error" element
  # Try creating one that ends at +30 days 1am
  When  I go to "/resource-booking/item"
  And   I view details for resource "A single projector"
  And   I start booking resource for "+30 days"
  When  I fill in datepicker "start_datetime[date]" with "+29 days"
  And   I fill in datepicker "end_datetime[date]" with "+30 days"
  And   I fill in "start_datetime[hour]" with "1"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "AM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "1"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "AM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a ".error" element
  Then  I should see "This item has already been booked for this time" in the "label.error" element

@resource-booking @resource-booking-errors
Scenario: Booking an item for less than min booking time
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "A single projector"
  And   I start booking resource for "+3 days"
  When  I fill in datepicker "start_datetime[date]" with "+3 days"
  And   I fill in datepicker "end_datetime[date]" with "+3 days"
  And   I fill in "start_datetime[hour]" with "3"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "PM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "10"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "PM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a ".error" element
  Then  I should see "This item must be booked for at least 1 day" in the "label.error" element

@resource-booking @resource-booking-errors
Scenario: Booking an item for more than max booking time
  Given I do not wipe the slate clean
  And   I go to homepage
  And   I log in as "rvollmer" with password "password"
  When  I go to "/resource-booking/item"
  And   I view details for resource "A single projector"
  And   I start booking resource for "+3 days"
  When  I fill in datepicker "start_datetime[date]" with "+3 days"
  And   I fill in datepicker "end_datetime[date]" with "+5 days"
  And   I fill in "start_datetime[hour]" with "3"
  And   I fill in "start_datetime[minute]" with "00"
  And   I select "PM" from "start_datetime[meridien]"
  And   I fill in "end_datetime[hour]" with "10"
  And   I fill in "end_datetime[minute]" with "00"
  And   I select "PM" from "end_datetime[meridien]"
  And   I press "resource-booking-save"
  And   wait for a ".error" element
  Then  I should see "This item may not be booked for more than 2 days" in the "label.error" element

# Test booking on a timetable
# Test approving of booking
