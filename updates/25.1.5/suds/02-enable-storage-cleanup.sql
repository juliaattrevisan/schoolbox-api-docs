-- Add this dsn fragment onto the end of existing veracross dsn

UPDATE config
SET value = 1
WHERE name = 'storage_cleanup_enabled';
