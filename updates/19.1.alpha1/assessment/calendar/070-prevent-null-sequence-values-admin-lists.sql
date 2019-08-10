-- This script updates the AdminList tables that have sequences to ensure those sequences
-- cannot be persisted to the database with a NULL or negative value.
-- This script MUST be run after the repair script that populates any unset values.
ALTER TABLE newsTopic
CHANGE COLUMN sequence sequence INT UNSIGNED NOT NULL;

ALTER TABLE lu_pastoral_severity
CHANGE COLUMN `order` `order` INT UNSIGNED NOT NULL;