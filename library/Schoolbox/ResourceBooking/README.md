# Schoolbox Resource Booking

## Enabling/disabling

Switch the config option `resource_booking_enabled` on/off.

## Packaging

The following files and directories are specific to resource booking, and can be
removed if resource booking is disabled:

    conf/modules/resourceBooking.php
    library/Schoolbox/ResourceBooking/
    library/Calendar/Api/EventType/ResourceBooking/
    library/Calendar/Event/Type/ResourceBooking.php
    library/Calendar/Event/Type/ResourceBooking/

## Resource booking URLs

* `/resource-booking/category` - A list of resource categories
* `/resource-booking/item` - A list of resource items
* `/resource-booking/item/$id` - View a resource, and view a calendar from which
  a booking may be made

## Usage

### Creating items and categories

* Superusers and users with the moderate permission on the new Resource Booking
  service may create and edit items and categories.

Creating resources and resource categories is mostly straightforward - here's an
explanation of some concepts which might not be.

#### Moderation type

* Open - any staff member may book this resource, and it will be confirmed.
* Moderated - any staff member may book this resource, but a moderator must
  confirm the booking.
* Managed - only moderators may book the resource.

#### Related Resources

An item may be set up with related items - at booking time, the booker will have
the option to create an identical booking for this item.
When updating the purpose of a primary booking, the purpose of all related 
bookings will be updated.

### Booking Resources

* All staff members may book resources (depending on the item's permissions - a
  resource may be set up to be bookable only by moderators).
* Non-staff may not book resources.

The user will be informed if:
* The booking time they have requested conflicts with another confirmed booking
* The booking has been requested with too little lead time (example - the item
  must be booked 2 hours in advance, and the user requested it 1 hour in advance)
* The booking has been requested for below its min reservation time, or above
  its max reservation time

#### Changing the time of a booking

If a user changes tht time of their booking, it must be re-approved.

#### Related Resource Booking

If the resource has related items, the user may choose to select them - if they
do, a separate booking item will be created for that resource at the same time.
These items must be approved separately.

#### Recurring Bookings

A resource may be booked on a recurring schedule - currently, either daily,
weekly, fortnightly or monthly. Doing so creates a new booking at each interval.

> TODO: add timetable-based recurring bookings

When updating a booking that is part of a recurring series, the user has the
option of updating all bookings in that series, or just that booking. If they
update all bookings in that series, then all the bookings in the series will
have their time changed by the same amount that the original booking was.

### Moderating Bookings

* Superusers and users with the moderate permission on the new Resource Booking
  service may moderate any booking.
* Users who are set up as moderators on a particular item may moderate bookings
  for that item only.

Bookings may be approved, rejected or cancelled from:

* The view resource page
* The resource booking dashboard component
* The calendar, if it's configured to show items which a user can moderate

#### Resolving conflicting bookings

There is some inbuilt conflict protection - users can't create bookings that
conflict with a booking that has already been confirmed - but there may be
instances where a conflicting booking gets created.

> TODO: display conflicts on the resource booking component

### Resource Booking Dashboard Component

If this component is enabled and added to the dashboard, users can moderate
their items from the dash.

### Resource Bookings on the Calendar

There are two calendar subscriptions:

* Resource Booking > Booking - A user's own bookings
* Resource Booking > Moderations - Bookings that the user can moderate

Users may view, edit and moderate bookings from their calendar.

> TODO: add an export for resource bookings
