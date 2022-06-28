-- This is now hardcoded to STUDENT group_types
-- and is no longer selectable in the ui
DELETE FROM `config`
WHERE `name` = 'report_roletype'
LIMIT 1;
