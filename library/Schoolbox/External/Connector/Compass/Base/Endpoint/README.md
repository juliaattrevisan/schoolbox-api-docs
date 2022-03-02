# Compass
The Compass importer accesses a set of API endpoints to fetch its data. It's also a bit special in that SUDS Compass ventures into new territory: importing timetable data as standard Schoolbox Events instead of trying to make them conform to our repeating rectangular timetable grid. There are currently some wonky quirks in that area which we'll cover in this doc.

# 1 Connector Setup
As usual we need two pieces of data for SUDS to work: *School Data Endpoint Host* and *School Data Endpoint Key*.

## 1.1 Endpoint Host
Compass API Endpoint Hosts generally follow a format like this:

```
https://<myschool>-integration-<state>.compass.education/api/
```

For example,

```
https://schoolbox-integration-vic.compass.education/api/
```

Put that into `School Data Endpoint Host`.

## 1.2 Endpoint Key
The Endpoint Key has two pieces of data:
* the key tuple itself, and
* a `timetableowner` tuple. The `timetableowner` specifies a schoolbx user account id that exists purely to own the timetable events that are spawned.

> Since we're importing timetable data as events, they must adhere to our usual schoolbox event rules such as having an owner of the event (the person whose calendar can see the event) and an author of the event (the person who created it, since you can make events for other people)

An example format is as follows, which assigns the `Schoolbox Admin` as the default author. Tuples are separated by a semicolon.

```
key=sgj235gp-g815-cc09-abh2-kiwe7h65x0o7;timetableowner=1
```

Put that into `School Data Endpoint Key`.

> Schools should elect a non-utilised user account to fulfil that purpose in the meantime with the caveat that this user's calendar will probably break due to the quantity of events they could own, since they're being assigned the owner of every event spawned in the import. For example, schools can create a user account "Compass Timetable" purely for the purpose of authoring timetable events.

# 2 Importing
Then run the sisImporter in the usual spot. It'll chomp through all the data.

```
vagrant ssh
cd /usr/share/schoolbox/tools/synchronise
php sisImporter.php -vvv --perform
```

This will import Profile data; it will also import Timetable data as Schoolbox Events.

# 3 Profile Data
The Compass API works based on two key pieces of data: a lower bound date and an upper bound date. These mean the same as _from_ and _to_ in a date range.

Since the SUDS importer was intended to run daily, I've arbitrarily chosen the lower bound (_from_) to be the start of the current day, and the upper bound (_to_) to be the end of the current day.

> The lower and upper bounds are specified identically in both Profile.php and Events.php which allow you to programmatically customise this to accommodate exotic scenarios if they emerge. We could distill that further up in Factory.php once we've done enough testing.

You should be looking at data only for today. If you're doing it for some other day, you might not get the results you're expecting.

## 3.1 Host Matching

Data is organised in a somewhat peculiar manner: a person in Compass can exist in multiple contexts (such as a staff member existing in two related schools) so to figure out which data is relevant we have to match on a url corresponding to the `School Data Endpoint Host`.

The Compass API is riddled with this philosophy and you have to manually sift through it every time. There's a `Helper` class which explains how this works in more detail and also illustrates where it's used.

### 3.1.1 Example
Take a look at the `GetOrganisations` endpoint data payload that Compass returns to us which contains this tuple:

```
"friendlyOrgId": "some host",
```

In a school that would typically look like this, which can match against `School Data Endpoint Host` with a high probability.

```
School Data Endpoint Host:
    https://schoolbox-integration.vic.compass.education/api/

API payload:
    "friendlyOrgId": "schoolbox-integration.vic.edu.au",

Intersect between these data:
    schoolbox-integration
```

In this case, if the first part of `friendlyOrgId` is a substring of `School Data Endpoint Host` then this is the record we want; in this example, `schoolbox-integration` is the common ground between the host and the api payload.

> There are numerous usages of this to identify data, because at Wyndham we found they had data shared with Heatherton and we needed to make sure we got the right data.
>
> A data record for John Smith would come with information relevant to both Wyndham and Heatherton campuses (and they could be different) so we use this technique to identify which data we want.

You can see other usages of `matchesHost()` via phpstorm.

# 4 Timetable Data
Compass Timetable Data goes into schoolbox as `events` like the ones you'd normally make via the fullcalendar screen(s). This is because we found it impossible to force the data to adhere to a repeating and predictable cycle like a classical timetable grid. Every day there could be variations in the times and quantify of periods being undertaken.

In the interim, we've just slapped these timetable events into the usual schoolbox calendar. They manifest in the `Event > Schoolbox Events` subscription; they currently do not have their own subscription.

> A SUDS-specific calendar subscription is being planned to give users more control over this data. At the moment you're going to get it mixed in with other events.

## 4.1 Preparation
However, this means we need to do a few more preparations in each import - purging the relationships of previously-imported timetable events. You'll see this as a set of purges when the importer beings processing timetable data.

As per our event system, anything imported from a third party gets an `event_source` string to identify the place from when an event came.

There's an `event_source` string corresponding to SUDS Compass in the `Prepare` class: `EVENT_IMPORT_IDENTIFIER`

## 4.2 Academic Groups
`Academic Groups` are Compass' concept that most closely resembles our Timetable Cycle. Their `academicGroupCode` values typically correspond to a year and each Academic Group has an `isRelevant` flag. So far there have been multiple Academic Groups but only one is marked as "relevant". That's the one we import.

> A school can configure their Academic Groups with the intent to show data from the future (or the past) but they'd need us to help them do that by configuring the lower and upper bounds.

> The importer would import any Academic Group whose isRelevant flag equates to true, but we haven't seen this occur in practice.

## 4.3 Schedules
`Schedules` are a collection of timetable events which Compass calls `Activities`. We'll delve into more detail about those momentarily.

Each school can have multiple Schedules. Each Schedule is a collection of thematically-similar events, like this:

```
Normal Classes
Staff Meetings
Staff Duties
Exams
```

It doesn't really matter much to us since they all end up in the same place - `Schoolbox Events` - but the logs will tell you which Schedules were destined to import and how many Activities are contained within.

> Schedules can be skipped if they don't allow imports via their `allowImport` flag which we've interpreted to mean exactly this.

## 4.4 Activities
Compass `Activities` are conceptually equivalent to our timetable events. Each `Activity` has a set of `Activity Instances`.

Similar to object-oriented classes in principle, the `Activity` describes what a timetable event entails and an `Activity Instance` is an occurrence of that event on a particular datetime with guys attending that event.

The output of the `GetSchedule` endpoint gives you a list of all the Activities and their corresponding Instances.

There are some inferred rules about Activities that permit (or prevent) them from entering the schoolbox database during an import.

An Activity must:
* be one of the known `Activity Types` listed by the const `ACTIVITY_TYPES`. We know of ten, as documented by Compass, but some schools are somehow accessing indexes beyond those ten.
* have a `runningStatus 1` (I've interpreted every other status to mean "not running" and therefore not import)
* have at least one corresponding `Activity Instance` otherwise the Activity doesn't take place and therefore has no functional meaning.

### 4.4.1 Activity Types
Depending on the Activity Type, we obtain involved people in different ways. There are two ways that involved people are specified:
* you have to go and find out, explicitly
  * Academically-oriented classes use a special enrolments endpoint `GetStandardClassDetails` to find out who's in that class. Such Activities have an Activity Type of `1`. We have to explicitly ask that endpoint who is involved in a particular class.

* it already comes with the payload from `GetSchedule`
  * Every other Activity Type is classified as a non-teaching timetable event and obtain their involved humans via the `attendees` node which already comes with the output of `GetSchedule`.

As per their parent Activity nodes, Activity Instances must also pass a set of criteria before they are imported. Activity Instances must:
* have a `runningStatus 1` (I've interpreted every other status to mean "not running" and therefore not import)
* start within a certain threshold of today, e.g. 7 days before today
* finish within a certain threshold of today, e.g. 30 days after today

These last two points allow you to populate the calendar with previous Activity Instances so you can see what you did yesterday or last week; also, so you can see what's coming up tomorrow and in the future. Otherwise you'd just have a timetable for today and you couldn't see where you had been yesterday and where you're going later. This date range is hardcoded in consts.

These consts are called `PAST_THRESHOLD` and `FUTURE_THRESHOLD` respectively. The larger this date span, the more data you install, but it'll go slower and hit the db more often. You can fiddle with it as you go, until you find a date range you like.

## 4.5 Events
When we find Activities, we import them as regular schoolbox events. They come in two flavours:
* group events, like on a homepage
* personal events

### 4.5.1 Group Events
These spawn as events for a homepage like you'd make with a Calendar component. If the folder code can be found at the time of import, it'll create a group event. Folder Codes must be the same as the Activity's name for this detection to take place, at which point records are added to `event_group` table.

### 4.5.2 Personal Events
If a homepage can't be found via the Folder Code lookup, then it'll create personal events instead. Everyone involved will get a copy of this event in their calendar via records in the `event_user` table.

> This unlocked the ability to share personal events with other people...

