-- schooldata_import_endpoint_key is now readonly and hidden from users
UPDATE `config` SET
`editable` = 'false',
`hidden` = 'true'
WHERE (`name` = 'schooldata_import_endpoint_key');

UPDATE `config` SET
 description = 'The https URL for your school data provider.  This is the API endpoint they provide to fetch data from for your school.'
  WHERE name = 'schooldata_import_endpoint_host';
