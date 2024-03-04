-- This config is now redundant: logging of original push payloads is now mandatory
DELETE FROM `config`
WHERE `name` = 'external_liss_storage_logging'
LIMIT 1;