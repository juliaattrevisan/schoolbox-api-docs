/**
 * Backup the mobile_app_registry table, as its format is going to change.
 *
 * (It may have already been created, in which case don't attempt it again.)
 */
CREATE TABLE IF NOT EXISTS mobile_app_registry_backup LIKE mobile_app_registry;
INSERT IGNORE INTO mobile_app_registry_backup SELECT * FROM mobile_app_registry;
