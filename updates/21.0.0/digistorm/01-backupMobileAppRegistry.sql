/**
 * Backup the mobile_app_registry table if it isn't already, as its format is
 * going to change.
 */
CREATE TABLE IF NOT EXISTS mobile_app_registry_backup LIKE mobile_app_registry;
INSERT INTO mobile_app_registry_backup SELECT * FROM mobile_app_registry;
