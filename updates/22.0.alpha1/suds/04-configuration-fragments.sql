-- schooldata_import_endpoint_key is now readonly
UPDATE `config` SET `editable` = 'false' WHERE (`name` = 'schooldata_import_endpoint_key');
