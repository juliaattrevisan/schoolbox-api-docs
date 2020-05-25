# Testing this thing

You'll need a set of Kamar xml files. You can download a bunch from a dev server in /mnt/data/cache/stream/kamar, or if they've already been processed, in a subfolder there called `archive`.

The Kamar importer absorbs a set of xml files that the Kamar software randomly vomits into the directory, so when we process them, we push them into an airlock system so if something breaks, we can analyse the data set that caused the breakage. When the next import runs, the archive is emptied into the void, then newly-processed xmls are pushed into the archive; thus, the lifecycle begins anew.

It's pretty convenient to keep a bunch on standby in a safe location so you can inspect contents and structure on-demand without having to do the rigmarole of shanking down files somewhere.

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

Visit https://vagrant.dev.schoolbox.cloud/adminv2/import and set the `Data Source` to `CSV Files`. Import the `Wang` timetable definition in Jira; it contains a sample setup.

> The timetable definition may currently be found attached to Jira ticket [SA-8090](https://schoolbox.atlassian.net/browse/SA-8090).

Import it via `Timetable Structure`. This sets up the Cycles for you.

Visit https://vagrant.dev.schoolbox.cloud/adminv2/import/schoolDataImport and set the `Data Source` to `Kamar`. Set up the `School Data Endpoint Host` and `School Data Endpoint Key` accordingly:

Endpoint Host: `/vagrant/data/cache/stream/kamar`
Endpoint Key: `test`

The Key isn't used yet, so you can just dump any text string in there for now.

## 3. Importing

Then run the sisImporter. The easiest way is to shank down a set of xml:

> TODO figure out where this XML comes from. I tested this with a data set Steve provided me, but AFAIK it's not available anywhere.

Then, pop it into the Endpoint Host directory, then run this:

```
vagrant ssh
cd /vagrant/tools/synchronise
# To protect against import of a partial data set, the Kamar sync won't run if
# any file is less than 15 minutes old.
# Set every file to have been modified yesterday to work around this.
touch -d "yesterday" /vagrant/data/cache/stream/kamar/*
php sisImporter.php --perform -vvv
```

This will start importing a bunch of dudes and their classes and stuff.

Obviously you can put those files wherever you want and square it away somewhere safe for convenience. It's going to take a little while, so read ahead while you wait.

### 3.1 Dates

Kamar data is time sensitive so if you're reusing an old data set, you'll need to impersonate the current time as well. For example, I have a data set from 2020 Term 1, but that term has long since finished. To simulate the loading of Term 1 data, I can change this datetime:

```
$weekOffsetCalculator = new WeekOffset(
    $this->timetableCycleMapper,
    Alaress_Date_Format::newFromTimestamp(strtotime('2020-01-29 00:00:00')),
    $this->log
);
```

## 4. Cycle Assignation

Visit https://vagrant.dev.schoolbox.cloud/adminv2/lists/campus

The two campuses `Senior` and `Junior` have a `Cycle` column currently set to `N/A`. Untick the one for `Senior` then choose a Cycle in the dropdown and hit `Save`. This assigns a Cycle to a Campus.

## 5. Assimilate Profile

Have a look in `sis_class_user` to find the external identifier for a student that is in at least one class.

Assign that external identifier to David Rhodes' user, then visit his Timetable via his Profile page; or, you can visit it directly https://vagrant.dev.schoolbox.cloud/timetable/230

All his stuff should display.

## 6. Reimport

Repeat step 3 to simulate what a crontab might do: The cycle assignation and timetable contents shouldn't change, nor should it arbitrarily create new Cycles for no apparent reason.

### 6.1 A Note About Cycles

Cycles are associated with Campuses via `luCampus.ext_timetable_code`. This field is synonymous with `sis_timetable_cycle.cycle_identifier` and both can be used to join data to each other.
