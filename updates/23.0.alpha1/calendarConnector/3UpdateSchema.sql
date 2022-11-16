# Deprecate the `config` column now that the replacement `config_json` column has been added and filled with data.
ALTER TABLE calendar_connector CHANGE COLUMN config _config text DEFAULT NULL;
