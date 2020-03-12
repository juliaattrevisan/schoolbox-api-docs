-- sis_class.code must be unique, to prevent dupe classes
-- from being inserted and manifesting in the timetable
ALTER TABLE `sis_class`
    DROP INDEX `code` ,
    ADD UNIQUE INDEX `code` (`code` ASC);

