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

Visit https://vagrant.dev.schoolbox.cloud/adminv2/import/schoolDataImport and set the `Data Source` to `iSAMS`. Configure:

- `School Data Endpoint Host`: the school-specific iSAMS host.
- `Batch XML Key`: the existing Batch API key, which is also the OAuth client ID.
- `Batch OAuth Client Secret`: the secret provisioned for the same school and Batch key with the `apiv1` scope.
- The day-number offset and parent relationship settings where required.
- The optional REST API credentials when higher-quality profile images are required.

The Batch OAuth client secret must be configured before iSAMS makes OAuth mandatory on 2 November 2026. Existing API-key-only configuration remains readable during migration.

## 3. Importing

Schoolbox obtains and reuses a Bearer token automatically for live imports. To download an authenticated XML fixture manually without putting the OAuth secret or token in the URL:

```bash
read -r -p 'iSAMS host: ' ISAMS_HOST
read -r -p 'Batch API key: ' ISAMS_BATCH_KEY
read -r -s -p 'Batch OAuth client secret: ' ISAMS_BATCH_SECRET
echo

TOKEN_RESPONSE=$(curl --silent --show-error --fail-with-body \
    --request POST "https://${ISAMS_HOST}/auth/connect/token" \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "client_id=${ISAMS_BATCH_KEY}" \
    --data-urlencode "client_secret=${ISAMS_BATCH_SECRET}" \
    --data-urlencode 'grant_type=client_credentials' \
    --data-urlencode 'scope=apiv1')

ACCESS_TOKEN=$(printf '%s' "${TOKEN_RESPONSE}" | php -r \
    '$response = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); echo $response["access_token"];')

curl --silent --show-error --fail-with-body \
    --header "Authorization: Bearer ${ACCESS_TOKEN}" \
    "https://${ISAMS_HOST}/xml.ashx?apikey=${ISAMS_BATCH_KEY}" \
    --output test.xml

unset ISAMS_BATCH_SECRET ACCESS_TOKEN TOKEN_RESPONSE
```

Then run the importer against the local fixture:

```bash
docker/ssh
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
