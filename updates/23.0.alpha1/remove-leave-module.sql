-- Delete all config variables pertaining to the boarder-leave module.
DELETE FROM config WHERE name LIKE "boarder_%";

-- Deprecate the leave_request table and associated lookup tables.
--  1. Remove qny foreign key links to tables to be removed.
--  2. Add underscore prefix to tables to be removed.
ALTER TABLE leave_request DROP FOREIGN KEY fk_leave_request_host_type_id;
ALTER TABLE leave_request DROP FOREIGN KEY fk_leave_request_travel_type_id;
RENAME TABLE lu_leave_request_host_type TO _lu_leave_request_host_type;
RENAME TABLE lu_leave_request_travel_type TO _lu_leave_request_travel_type;
RENAME TABLE leave_request TO _leave_request;


-- Remove database config for Board Leave page.
DELETE FROM config
WHERE type_id IN (
   SELECT id
   FROM lu_config_type
   WHERE type='Board Leave'
);
DELETE FROM lu_config_type WHERE type='Board Leave';
