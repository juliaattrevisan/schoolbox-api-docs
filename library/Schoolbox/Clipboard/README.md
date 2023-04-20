# Clipboard

Clipboard is a third party who manages events. These events are vaguely synonymous with our Fixtures in that they are competitive matches between two teams, but Clipboard goes a bit beyond that to include other kinds of events that aren't competitive, but are scheduled as if they were competitive matches. Examples of this include music practice and orchestra rehearsals, debating, and other extracurricular activities that aren't so traditionally "sporty".

## 1. How it works
To do it, we built an integration that completely hijacks the Schoolbox Fixtures module. The integration consumes data directly from the Clipboard API endpoint, saving Sports, Divisions, Teams, Players, Positions, Matchups and Venues into Fixtures. It's as if you had entered it all yourself, but faster.

However, this means that schools should use Clipboard as the source of truth. Any changes made in Schoolbox will be overwritten and/or discarded the next time the Clipboard Importer runs. This uses the same principle as SUDS connectivities.

### 1.1. Divisions
Clipboard doesn't have the concept of _Divisions_ the way Schoolbox has defined them. To simulate Clipboard Divisions we use a concatenation of Clipboard _Category_ and (if present) _Subcategory_. Things might look a bit funny in the ui for this reason.

This concatenation is performed via a `Division` codec in Schoolbox.

## 2. Setting up
To enable Clipboard, you just need two pieces of data.

* **Clipboard Endpoint Host**. This is a universally shared url, `http://api.clipboard.app` which every school uses
* **Clipboard Endpoint Token** which is obtained via Clipboard themselves and is unique to each school

### 2.1. crontab
The importer runs every four hours (approx) as specified in the crontab. There's an offset and jitter to stop it from overloading.

### 2.2. External Identifiers
Note that external identifiers in Clipboard should match the external identifiers in Schoolbox User accounts. If they don't match up, participating players won't be discovered and you'll be missing people from Teams. Among other debug data, the debug verbose log tells you this:

```
Clipboard human Robert Nichols (externalId 12142) does not have a corresponding schoolbox user record; skipping
```

## 3. Command Summary
The Clipboard Importer was originally built as a two-step import process but I found it was easier to just import everything together. It didn't seem to be punishingly slow.

There are a few options given to you when importing via the CLI command. They are described below.

### 3.1. Memberships Import
This command imports only Seasons, Sports, Divisions, Teams, Players, Positions. 

`php /usr/share/schoolbox/clipboard/importer.php --membership --perform -vvv`

### 3.2. Sessions Import
This command imports only Matchups, Coaches and Venues. The combination of these three pieces of data is known as a Clipboard _Session_.

`php /usr/share/schoolbox/clipboard/importer.php --sessions --perform -vvv`

### 3.3. Import everything
Typically you'll want everything. To do it you can combine the flags:

`php /usr/share/schoolbox/clipboard/importer.php --membership --sessions --perform -vvv`

This is the command specified in the crontab. Alternatively you can import partially, as described in the preceding sections.

## 4. Partial Updates

This mode utilises `updatedBefore` and `updatedAfter` args in the two endpoints for Teams and Sessions. Partial Updates will cause Clipboard to filter their result set to Teams and Sessions that were updated or added between these two timestamps, allowing us to have a higher frequency of import.

To do it, simply supply `--partial` as a flag.

`php /usr/share/schoolbox/clipboard/importer.php --membership --sessions --partial --perform -vvv`

In the Clipboard logs, you'll see a message like this when it's in Partial Update mode:

`Obtaining partial updates for Teams updated between 2023-04-20T18:10:11+10:00 and 2023-04-20T18:34:41+10:00`

### 4.1 Import Log

`clipboard_import_log`, like its SUDS counterpart, remembers all the times an import runs; importantly, it knows about the last successful import. 

This row's `finish_at` is used as the `updatedAfter` arg described above. `updatedBefore` is always the current datetime. This results in a Partial Update to fetch data that changed between now and the most recent successful run.

> If the importer has never run before, `updatedAfter` defaults to the start of the current day.

There is currently no user interface to see the contents of clipboard_import_log, but it functions identically to `sis_import_log`.

### 4.2 Deletions

Clipboard currently doesn't have a mechanism by which to detect when data was deleted in Clipboard. In this circumstance, the deleted thing won't come out of their API endpoints so we won't know about it.

For now, Partial Updates mode should run in conjunction with the full import to address this.