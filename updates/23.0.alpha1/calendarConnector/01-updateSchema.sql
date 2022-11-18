# Add column `config_json` to replace column `config`.  The `config` column will be renamed to `_config` after the data has been migrated.
ALTER TABLE calendar_connector ADD COLUMN _config text DEFAULT NULL AFTER config;

# Backup contents of `config` to `_config`.  Column `config` will be overwritten in the next step (02-migrateData.php).
UPDATE calendar_connector SET _config=config
WHERE config<>''; # Do not overwrite data in _config with blank string (to avoid losing data while testing).

# Clear contents of `config`.  We do this only so that the WHERE condition in the above query has the desired effect.
UPDATE calendar_connector SET config='';

# Disallow nulls so we don't need to deal with them in code.  A blank string will mean the same as NULL, so we don't need NULL.
ALTER TABLE calendar_connector CHANGE config config text NOT NULL;
