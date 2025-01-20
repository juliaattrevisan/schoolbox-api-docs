-- Remember the strats requested by user
-- This allows for user-initiated Timetable-only syncs, for example

alter table sis_import_queue
    add strats varchar(255) null after user_id;
