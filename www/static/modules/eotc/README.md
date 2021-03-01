# EOTC Education Outside The Classroom
This is a custom module originally built for St Kentigern. It introduces a moderation queue to Events. The moderation queue is fulfilled by a number of humans; these are known as EOTC Administrators and they are defined by their own set of permissions. Each EOTC Administrator is responsible for one or more statuses. When the final status is reached, the EOTC event is made "official" and public, announced to relevant parties. A couple of other details are present, but for the most part, EOTC events function similarly to regular Calendar events.

## 1. Schoolbox Installation
There are a couple of schoolbox installation settings that need to be set up for EOTC to function properly. They are reasonably common oversights. Unfortunately they aren't immediately obvious to the uninitiated.

### 1.1 Enable Module
This is an opt-in module. It must be enabled by Support. You can find out whether it's enabled by visiting http://192.168.36.10/adminv2/setting/Modules. It is already on by default in our vagrants.

### 1.2 Enable Calendar Subscription
http://192.168.36.10/adminv2/calendar/Subscribe

EOTC Events won't appear in any calendars until the subscription has been enabled. It's off by default in our vagrants. This also prevents you from exporting EOTC Events so remember to switch it on.

### 1.3 Localisation
http://192.168.36.10/adminv2/terminology

EOTC terminology can be localised for each schoolbox installation. In our vagrants, the default terminology scheme is the original St. Kentigern implementation. Each school should customise the items here as appropriate.

### 1.4 Side Menu
When you first try it out, the EOTC navigation makes no sense. It'll feel disjointed and somewhat awkward. This is because it was implemented for multiple people, each of whom have their own preferences as to what's important and what isn't. Therefore there isn't a navigation system that works perfectly for everyone. To this end we recommend creating Side or Top menu shortcuts to each of the pages described in this document. This enables each school to set up their EOTC Administrators in a meaningful manner.

## 2. Glossary
- *EOTC Administrator.* A staff member who has at least one EOTC Permission. Some contexts restrict their ability based on campus (such as status transitioning) but some don't (such as calendar)
- *EOTC Manager.* Previous terminology, now deprecated. See *EOTC Administrator*.
- *RAMS File.* Risk Assessment Method Statement. This document describes how risks will be dealt with on excursions. Other non-abbreviated forms: *Risk Assessment and Management Strategy*, *Risk Assessment Management System* (I'm not actually sure which one is official, if any)


## 3. Configuration
http://192.168.36.10/modules/eotc/config/modify

You can configure various behaviours of EOTC here. 

> Coders: it lives in its own section because once upon a time it was a custom module for a school. It hasn't been migrated into a conditional in adminv2/home yet.

- *First Reminder.* An int corresponding to the number of days prior to an event's intended start datetime. An email-based notification is sent to all EOTC Administrators capable of approving the event when this threshold is reached and the event hasn't yet been approved. Example: If First Reminder is set to 10, then a notification is sent ten days out from any non-approved EOTC event's scheduled start.

- *Daily Reminder.* Similar to First Reminder, but occurs daily until an event is approved. A more aggressive First Reminder. Example: If Daily Reminder is set to 3, then a notification is sent daily, three days out from any non-approved EOTC event's scheduled start.

- *Urgent Threshold.* Events are reallocated into their own unique section "Urgent" when this threshold is reached. Example: If Urgent Threshold is set to 3, then three days out from the event's scheduled start, it will be listed in its own special section in the Review screen.

- *EOTC Policy.* A custom html blob.

- *RAMS Warning Message.* A preamble in a modal that appears when you cick the *RAMS is required* checkbox. This is a custom message you can display to remind people to supply risk assessment documents etc. Note that RAMS is a localised term and you can customise it per school.

- *External DB Sync.* When this is on, events that enter Approved status can be uploaded into Synergetic's Excursion Module. This only works with Synergetic. Default setting is off.

- *Mandatory Student Attendees.* When this is on, student attendees are required for an event to enter Approved status. Default setting is on.

## 4. Permissions
http://192.168.36.10/modules/eotc/permission/modify

EOTC Administrators are spawned here. You have to spawn one person at a time (the selectize and php isn't set to accept multiple people). These individuals are able to manipulate various event details etc. for EOTC events specifically.

You can configure individuals to have access to particular statuses which they can transition. This breaks the moderation queue down and distributes responsibilities across multiple EOTC Administrators. EOTC Administrators can only manipulate the status of an event if they have access to the same status to which the event belongs.

EOTC Administrators can only manipulate the status of an event if they have the same campus to which the event belongs.

The Subscriptions checkboxes correspond to notifications that are received when an event enters a status that can be managed by an EOTC Administrator.

## 5. Policy
http://192.168.36.10/modules/eotc/policy

An arbitrary ckeditor blob displays here. It is composed in Configuration.

## 6. Add/Update EOTC Events
http://192.168.36.10/modules/eotc/manage/modify
http://192.168.36.10/modules/eotc/manage/modify?eventID=:eventId

You can alter EOTC events in a largely-synonymous manner as regular Calendar Events, with a small buffet of extra EOTC-specific features.

### 6.1 People 
- *Teacher In Charge.* This is intended to be the main teacher who is supervising the excursion. It can be any staff member.

- *Staff Members.* These are staff attendees. Typically there aren't more than a handful of staff on excursions so most of the time it's okay to display them all in a selectize. They will now appear in the Attendance List as of 2017.

- *Student Attendees.* This is a count of the current number of student attendees. Their names used to appear here but there were so many we put them on a different screen entirely.

#### 6.1.1 Manage Attendances
You can manage student attendances from here. There are two different ways you can add students. In either case, you're still able to tweak the attendees from the Current Attendees section below.

- *Directly*, by using the Add Individual Student selectize. This allows you to add specific individuals 

- *By Course Code*, by using a Synergetic lookup. This adds everyone in the chosen Synergetic FileType to the EOTC event; in this case, FileTypes are Academic, Community Service, etc. which are all the options that you see in the Category dropdown. Yes, "FileType" is not a great name for this kind of information.

> Coders: The FileType and their populations are configurable in Synergetic. Note that a Synergetic FileType is just a generic way of describing a "category" or "type" of something. 

### 6.2 RAMS Required
You can upload RAMS Files here. It can be any document permitted by the schoolbox installation. The term RAMS is localised.  It is up to the EOTC Administrator(s) to determine whether these attached files' contents are suitable.

### 6.3 Complete Details (Modify Only)
This is a special section that appears when the event is in the status *Approved In Principle* and you're modifying it. This section contains a checkbox titled *All the required information for this event has been provided*. If you tick this checkbox, it transitions into the next appropriate status without further intervention by an EOTC Administrator. This action can be performed by the Event Creator or its Teacher In Charge.

> Coders: programmatically, this is known as Self Promoting. This allows for extra expedience by allowing Event Creator and Teacher In Charge to promote an event from Approved In Principle to the next status.

### 6.4 Permission Slip Required
This is a switch that indicates this event should be uploaded into Synergetic's excursion module if/when it enters the Approved status. See *10. External Database Sync*.

## 7. My EOTC Events
http://192.168.36.10/modules/eotc/myReview

Used by Event Creators.

A list of EOTC Events that you have created. They are ordered by start date and start time. You can click on the pencil to edit these events, or the + icon to spawn more. You can see each event's status here.

## 8. Review EOTC Events
http://192.168.36.10/modules/eotc/review

Used by EOTC Administrators.

This now redirects to the Event Report, with sensible defaults:

* From: 6 months ago
* To: empty (no limit)
* Statuses:
    * Initial Application
    * Approved In Principle
    * Details Completed
    * Ready For Final Approval

## 9. Status Transition
http://192.168.36.10/modules/eotc/status/modify/:eventId

This screen is accessed mostly by EOTC Administrators who intend to transition an event from one Status to the next. Optionally, a comment may be provided if desired. 

When viewed by an EOTC Administrator, the Change Status dropdown is limited to the current status, the next available status, and Cancelled. Once a status is set, it cannot be reverted except by a schoolbox superuser.

> Coders: programmatically, Status Transitioning is known as Promoting. You'll find a couple of references to promoting throughout EOTC.

### 9.1 Available Statuses
There are six possible statuses for EOTC Events. An EOTC Event can only be in one status at any given time. 

1. *Initial Application.* The event is basically an idea that requires an EOTC Administrator to conclude that this event has potential to take place.
2. *Approved In Principle.* An EOTC Administrator has concluded that this event can take place. 
3. *Details Completed.* Event Creator has supplied RAMS files if the RAMS Required checkbox was ticked. This status is conditional upon this checkbox and won't appear if RAMS was not required.
4. *Ready For Final Approval.* Event Creator has provided everything that the event requires - attendees, resources, locations etc and is ready to evaluate in fullness.
5. *Approved.* EOTC Administrator is okay with the event and it can go ahead.
6. *Cancelled.* Event will not take place. Any status can transition to Cancelled.

Transitions usually occur like this:
* Typical transitions when RAMS is required: 1 > 2 > 3 > 4 > 5
* Typical transitions when RAMS is not required: 1 > 2 > 4 > 5

Note that the EOTC Administrator permissions are on a status *and* campus basis. An EOTC Administrator must have permissions on the status and the event's campus in order to transition its status.

### 9.2 Attendance List (CSV)
This is a CSV export of all the attendees. This now includes Staff Attendees in addition to Student Attendees, which was the pre-2017 implementation. The CSV itself is self-explanatory and fairly bleak so there shouldn't be much difficulty understanding it.

### 9.3 Attendance List (HTML) 
This shows a list of all the attendees in your browser, along with their profile pictures. There's a convenience link to the CSV format if required. It might benefit from a staff and student count as per the Status Transition screen.

### 9.4 Activity Log
Every status transition is logged in the creatively-named Activity Log section. Any accompanying commentary is also shown here. There is nothing you can do with this log except to read it.

## 10. External Database Sync
We added an ability to synchronise EOTC Event data into Synergetic's excursion module. It only works for Synergetic. Schoolbox will ignore any other SIS in this use case.

The synchronisation requires two items to proceed.

- *External DB Sync is switched on.* 
- *Permission Slip Required is switched on.*  

As previously explained, the first is a global setting. The second is a per-event setting. This allows synchronisation to occur on a per-event basis. If both items are switched on, when the event enters Approved status, it'll automatically be uploaded into Synergetic. You can find it in synweb under *Student Maintenance > Excursion Maintenance*.

## 11. Event Report
http://192.168.36.10/modules/eotc/report

This is a list of EOTC events that is filtered by status. By default, it shows events that:

* start today, and:
* are in one of these statuses:
    * Initial Application
    * Approved In Principle
    * Details Completed
    * Ready For Final Approval

This screen shows a list of events, grouped by Status. When certain thresholds are reached, an event may show in either
its Status grouping, or in its own unique case, Urgent. This is determined by the Urgent Threshold setting in EOTC
Configuration.

You can view an individual event by hitting the eyeball. This takes you to the Status Transition screen.

It does not by default show all events, because the event list became extremely long and slow to load.
However, you can view the entire unabridged list by selecting *All* as the timespan, and clicking *Search*.

## 12. Display In Calendar
When you see EOTC Events in the calendar, there'll be a little numeric segment after the event's title. Example: *My EOTC Event (5,2)*.

The numbers in brackets *(5,2)* indicate the number of student attendees and staff attendees respectively. In its minimal form, it'll display *(0,1)* since there is always at least one staff member associated with any EOTC Event (the Teacher In Charge is assumed to always be an attendee). 

The counts are to help with catering. You can still get these counts in the Modify screen.

### 12.1 Visibility
EOTC Events become visible to certain parties based on the event's current status. The styling will depend on their status, however, in accordance with the following:

- Events display as translucent when they are in any non-approved status except Cancelled.
- Events display as opaque when they are approved.

On the calendar and in the export, in accordance with the aforementioned styling convention:
- EOTC Events are only visible when the module is available and when the calendar subscription is on. See *1. Schoolbox Installation* 
- EOTC Events are visible to Superusers and EOTC Administrators.
- EOTC Events are visible to its creator.
- EOTC Events are visible to Teachers In Charge (TIC).
- EOTC Events are visible to any staff member in the same campus as the event itself.
- EOTC Events are visible to Staff Attendees.
- EOTC Events are visible to Student Attendees only when in Approved Status.
- EOTC Events are visible to the selected Campus and Role combination only when in Approved Status.  

#### 12.1.1 Roles
The Role checkbox indicates which Roles can view the event. If you don't select any roles, the event will be hidden from anyone who isn't somehow involved in it as per the list of conditions in *12.1 Visibility*.

Otherwise the Role checkbox selection works in conjunction with a campus checkbox selection. This should be interpreted as "Make this EOTC Event visible to people in the selected Role(s) and also in the selected Campus(es)".

### 12.2 Exporting Events as Vcal
You can export EOTC Events like any other. What you see on the calendar is what you'll get in the export; the EOTC Connector doesn't have any idiosyncratic hard-coded logic. Therefore, if you want to export a list of particular EOTC Events, you need to set up a faux user who can see these events, and have this user Export to csv or url.

## 13. Other References
You can find an index of articles at https://help.schoolbox.com.au/homepage/1504
