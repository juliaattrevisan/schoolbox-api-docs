-- schooldata_import_endpoint_key is now readonly and hidden from users
UPDATE `config` SET
`editable` = 'false',
`hidden` = 'true'
WHERE (`name` = 'schooldata_import_endpoint_key');
