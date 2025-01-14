# Testing this thing

The iSAMS importer shanks down an xml file from the endpoint. You'll need one of those if you want to be speedy, otherwise it'll go and download one every time you run the importer. You can specify a source file using the `sisImporter.php` tool; `sisImporter.php --help` tells you more.

That xml file will contain everything from Profiles to Timetable data. We use `xpath` to roam around the xml and eat everything we need. The xml files are generally quite girthy so it's convenient to have one on standby in a safe place.

## 1. Purge Legacy Data
You'll have to purge legacy data's profile first. It contains broken data which is now strictly disallowed from existing. In isams, there's a user with external id 71 which clashes with someone in legacy data who is broken. Base Profiles can't exist on their own.

To prepare a realistic scenario,

```
delete from sis_user_profile_relation;
delete from sis_user_profile_staff;
delete from sis_user_profile_student;
delete from sis_user_profile_parent;
delete from sis_user_profile;
delete from sis_class_period;
delete from sis_class_user;
delete from sis_class;
delete from sis_timetable_period;
delete from sis_timetable_cycle;
```

## 2. Connector Setup

Visit https://vagrant.dev.schoolbox.cloud/adminv2/import/schoolDataImport and set the `Data Source` to `iSAMS`. Set up the `School Data Endpoint Host` and `School Data Endpoint Key` accordingly.

For testing, you can use an endpoint host and key from a Schoolbox instance which already uses iSAMS (e.g. GEMS):
visit the instance's School Data Import admin section and copy the endpoint host and key from there.

## 3. Importing

Then run the sisImporter. The easiest way is to shank down an xml:

```
wget https://{School Data Endpoint Host}/api/batch/1.0/xml.ashx?apikey={School Data Endpoint Key} -O test.xml
```

Then, pop it into the www directory as test.xml, then run this:

```
vagrant ssh
cd /usr/share/schoolbox/tools/synchronise
php sisImporter.php --perform -vvv --file ../../www/test.xml
```

This will start importing a bunch of dudes and their classes and stuff.

Obviously you can put that file wherever you want and square it away somewhere safe for convenience. It's going to take a little while, so read ahead while you wait.

## 4. Cycle Assignation

Visit https://vagrant.dev.schoolbox.cloud/adminv2/lists/campus

The two campuses `Senior` and `Junior` have a `Cycle` column currently set to `N/A`. Untick the one for `Senior`. In the dropdown there'll be just one option for you to choose; choose wisely, then hit `Save`. This assigns a Cycle to a Campus.

## 5. Assimilate Profile

Have a look in `sis_user_profile` and find a student's external identifier. They have a very long `external_id` value, ~15chars.

Assign that external identifier to David Rhodes' user, then visit his Timetable via his Profile page; or, you can visit it directly https://vagrant.dev.schoolbox.cloud/timetable/230

All his stuff should display, and Day 3 is a little bit offset in the grid. It's meant to be like that. Their midweek is weird.

## 6. Reimport

Repeat step 3 to simulate what a crontab might do. The cycle assignation and timetable contents shouldn't change, nor should it arbitrarily create new Cycles for no apparent reason.

### 6.1 A Note About Cycles

 If a cycle already exists for the given `PublishedTimetable` identifier (an integer) then that cycle gets reused. Otherwise, it'll truncate `sis_timetable_cycle` and create a new cycle, to prevent the accumulation of empty cycles that have since been obsoleted. If I didn't do that, someone would have to manually assign cycles every time the importer ran (nightly) which is, of course, an absurd requirement. Now you only need to manually assign it if the timetable changes, typically at school rollover times like when a term ends; that's when a new `PublishedTimetable` integer is defined and it's stored in `sis_timetable_cycle.cycle_identifier`.

Cycles are associated with Campuses via `luCampus.ext_timetable_code`. This field is synonymous with `sis_timetable_cycle.cycle_identifier` and both can be used to join data to each other.

## 7. Portraits

We now have 2 methods for importing portraits as of (25.0)

BatchXML (Pre 25.0) this downloads a zip for each role type (staff/student) containing a collection of images each named with the 'personID' of the user.
We then import that and clean the temp files.

RestApi (25.0) this now hits unique endpoints for staff/student confirms the latest photo ID from the sis
matches this ID against the metadata field in sis_user_profile. If these differ we download the image, update the profile and cache the latest photo ID

This can be called with its on --strats=photos (if there are any profiles that have been imported) (must include --strats students/staff)
