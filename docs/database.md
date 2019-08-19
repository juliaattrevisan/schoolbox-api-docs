# Database Standards

**Tables:** InnoDB

**Charset:** utf8mb4

**Collation:** utf8mb4_unicode_ci

### Database
The core database is either called "schoolbox" or "schoolboxdev", depending on the environment it is running in.  All scritps should avoid using the database name as it may change based on the environment.

### Naming Conventions
We use snake case for our naming convention. Tables should be first named with their module then the entity.  For example "pastoral_comment".
Common field names include; id, created_at, updated_at, modified_by, deleted_at.

**Foreign entity identifier field names:** foreignentity_id

**Foreign keys should be named:** fk_currenttablename_fieldname

**Indexes should be name:** idx_currenttablename_fieldname


### Tables
All tables should now be created with InnoDB.  Where applicable please create **foreign keys**.  In most cases you should not use referential CASCADE actions.  Instead these should be kept in business logic unless absolutely required.  Use RESTRICT or NO ACTION on both update and delete.  

For now please avoid using stored procs or events on tables. We want to keep business logic out of the schema unless it is required.

### Charset (the way we store text)
We have now converted almost everything to the utf8mb4 charset. utf8mb4 is an extension of utf8 and gives the complete charset of utf8. 
https://mathiasbynens.be/notes/mysql-utf8mb4

In some cases we still use ascii and these should remain for various performance and storage space reasons.  Examples are crypto keys, hashes, tokens and other items that are strictly in ASCII.

### Collation (the way we sort text)
We are now using utf8mb4_unicode_ci for all future tables.  It will sort correctly and fix all issues.

https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
