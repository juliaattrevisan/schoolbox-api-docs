-- Deprecate the leave_request table and associated lookup tables.
--  1. Remove qny foreign key links to tables to be removed.
--  2. Add underscore prefix to tables to be removed.
ALTER TABLE leave_request DROP FOREIGN KEY fk_leave_request_host_type_id;
ALTER TABLE leave_request DROP FOREIGN KEY fk_leave_request_travel_type_id;
RENAME TABLE lu_leave_request_host_type TO _lu_leave_request_host_type;
RENAME TABLE lu_leave_request_travel_type TO _lu_leave_request_travel_type;
RENAME TABLE leave_request TO _leave_request;

ALTER TABLE user CHANGE boarding_status _boarding_status varchar(255) null;

-- Remove database config for Board Leave page.
DELETE FROM config
WHERE type_id IN (
   SELECT id
   FROM lu_config_type
   WHERE type='Board Leave'
);
DELETE FROM lu_config_type WHERE type='Board Leave';


DELETE FROM config WHERE name LIKE "boarder_leave_enabled";

DELETE FROM ups_effect WHERE user_property = 'boardingStatus';

-- Delete no longer used error constant.
DELETE
FROM error
WHERE id = 162; -- Expected error message: 'Leave request commences too soon.'

