# Add column `config_json` to replace column `config`.  The `config` column will be renamed to `_config` after the data has been migrated.
ALTER TABLE calendar_connector ADD COLUMN config_json JSON DEFAULT NULL AFTER config;
