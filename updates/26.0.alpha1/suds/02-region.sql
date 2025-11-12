-- Rename authhost to region
-- set it to either "Australia" or "Global" depending on the url that already existed here
-- due to the execution of 01-authhost.sql and its evolution into master

UPDATE config
SET value = REPLACE(value, 'authhost=https://accounts.veracross.au', 'region=Australia')
WHERE name = 'schooldata_import_endpoint_key'
  AND 'veracross' = (SELECT value FROM config c2 WHERE name = 'schooldata_import_data_source' LIMIT 1)
LIMIT 1;

UPDATE config
SET value = REPLACE(value, 'authhost=https://accounts.veracross.com', 'region=Global')
WHERE name = 'schooldata_import_endpoint_key'
  AND 'veracross' = (SELECT value FROM config c2 WHERE name = 'schooldata_import_data_source' LIMIT 1)
LIMIT 1;
