Schoolbox updates folder
===

All database update scripts for each version go within this folder.


Structure
---

    $versionNumber/
        subFolder/
            subUpdateScript1.{sql,php}
            subUpdateScript2.{sql,php}
            ...
        updateScript1.{sql,php}
        updateScript2.{sql,php}
        ...

The first folder level is the Schoolbox version number.

Each $versionNumber folder may contain one or more:
* SQL scripts
* PHP scripts
* Subfolders, which may in turn contain SQL or PHP scripts

Any scripts not in a $versionNumber folder will be ignored.


Script execution
---

On version upgrade, only the scripts for the versions between current and
target versions will be run.

For example: if a Schoolbox is on version 16.5.12,
and it is updated to version 17.0.0, then only the scripts for versions
between 16.5.13 and 17.0.0 will be run.


Order of execution
---
1. $versionNumber folder, by version number (`sort -V`)
    * `sort -V` counts "alpha", "beta" and "rc" as before 0, so "17.0.alpha1"
      gets run before "17.0.0"
2. Script path within each $versionNumber folder, alphabetically (default `sort`)

Example ordering of script execution within a version number folder:

    assessments/500-promote_work_type_to_assessments.sql
    cleanupDeprecated.sql
    eportfolio/500-showcase_item-column-definitions.sql
    EportfolioSurvey.sql
    filters/100-add_filter_tables.sql
    foreign_keys/blogFK.sql
    foreign_keys/wikiFK.sql
    localisation/01-localiseGrades.sql
    localisation/08-addRAMSWarningConfig.sql
    notifications/500-transfer-queues-to-lists.php
    pastoral/01-migrate_tags.sql
    pastoral/02-pastoral_refactor.sql
    removeFileIDVideoQueue.sql
    removeSkinBuilderConfig.sql
    testFunction.sql
    userDisplayGradeAverage/01-updateConfigDescription.sql
    userDisplayGradeAverage/02-addField.sql
    userDisplayGradeAverage/03-setFieldValue.sql
    webdav.sql
    zzz-charset/000_collation.php

Note that scripts in subfolders are not necessarily executed before or after
scripts in the version number folder.


Rules
---

* All new database tables must be InnoDB
* All new database table and column names must be in snake_case format
* All new database column names must have appropriate foreign key constraints
    * the `NO ACTION` constraint type should be used for `ON UPDATE` and
      `ON DELETE`, so that deletes don't cascade without warning
* All new database columns must be in either the `ascii` or `utf8mb4` character set
    * This SQL command will find all columns not in one of these charsets:
      ```
      SELECT
      table_name, column_name, data_type, character_set_name, collation_name
      FROM information_schema.columns
      where TABLE_SCHEMA='schoolbox'
      AND COLLATION_NAME IS NOT NULL
      AND character_set_name not in ('utf8mb4', 'ascii')
      order by table_name asc, column_name asc
      ```

* PHP scripts may not use `Mapper` classes for database access; they must
  run queries directly.
    * This is because an update may be run from a version that doesn't yet
      implement a mapper, or implements it differently - this means that the
      update could run differently based on the current Schoolbox version.


### Special rules for dropping tables or columns

If it is necessary to drop a table or column: its data must be kept intact for
at least one major Schoolbox version's lifespan:
this is so that any migrations which are based on the column's or table's
contents can be replayed in the case where the original migration went wrong.

This is handled by:
* removing all foreign key relations to the table/column to be dropped
* marking the table/column as deprecated, by prefixing its name with a `_`
  character:
  ```sql
  # Dropping a table, part 1: deprecation
  RENAME TABLE blog TO _blog;
  # Dropping a column, part 1: deprecation
  ALTER TABLE blog CHANGE folder_id _folder_id INT(6) UNSIGNED NOT NULL;
  ```
* drop the deprecated table/column no sooner than one major release after the
  deprecation has been released to production instances:
  ```sql
  # Dropping a table, part 2: complete drop
  DROP TABLE _blog;
  # Dropping a column, part 2: complete drop
  ALTER TABLE blog DROP COLUMN _folder_id;
  ```
  * this means that (for example) if the deprecation occurred in the 20.1
    development cycle, then the drop may happen no earlier than the 20.2
    development cycle.


### Special rules for renaming tables or columns

Renaming a table or column is effectively the same as dropping it and creating a
new one: this means that the table or column cannot be renamed directly.
```sql
# Incorrect
ALTER TABLE link CHANGE category folder_id INT(5) UNSIGNED DEFAULT 0 NOT NULL;
```

Instead: the old name should be deprecated, a new name added and data copied
from old to new:
```sql
# Correct

# Part 1: deprecation and migration
ALTER TABLE link CHANGE category _category INT(5) UNSIGNED DEFAULT 0 NOT NULL;
ALTER TABLE link ADD folder_id INT(5) UNSIGNED DEFAULT 0 NOT NULL;
UPDATE link SET folder_id = _category;

# Part 2: complete drop (in the next major version)
ALTER TABLE link DROP COLUMN _category;
```


### Special rules for repurposing tables or columns

Tables or columns may not be repurposed: this is effectively the same as
dropping and creating a new one, as the original purpose of the table or column
is lost.

Instead: a new column should be added for the new purpose, and the old column
deprecated.


Conventions
---

Where possible, prefer SQL update scripts over PHP, as they are generally
simpler to understand. A PHP update may be preferred over SQL when:

* The logic used to determine new field values is not easily expressed in SQL
* The update is potentially destructive enough to require a log of what is
  affected by the update

Subfolders are generally used to group updates that pertain to a particular
feature.

Some scripts need to be run in a particular sequence. In order to ensure that
execution order is preserved, the file name is often prepended with a number.
Example:

    userDisplayGradeAverage/01-updateConfigDescription.sql
    userDisplayGradeAverage/02-addField.sql
    userDisplayGradeAverage/03-setFieldValue.sql
