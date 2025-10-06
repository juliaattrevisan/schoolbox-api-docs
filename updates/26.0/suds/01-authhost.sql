-- Add this dsn fragment onto the end of existing veracross dsn

UPDATE config
SET value = CONCAT(value, ';', 'authhost=https://accounts.veracross.com')
WHERE name = 'schooldata_import_endpoint_key'
AND 'veracross' = (SELECT value FROM config c2 WHERE name = 'schooldata_import_data_source' LIMIT 1)
LIMIT 1;
