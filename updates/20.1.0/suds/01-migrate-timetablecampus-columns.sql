-- Add columns to timetable cycle, where they should hve been all along

ALTER TABLE `sis_timetable_cycle`
    ADD COLUMN `start` DATE NULL AFTER `regular`,
    ADD COLUMN `finish` DATE NULL AFTER `start`,
    ADD COLUMN `start_day` INT NULL AFTER `finish`,
    ADD COLUMN `offset` INT NULL AFTER `start_day`,
    ADD COLUMN `reset` INT NULL AFTER `offset`;

-- Biggen the Timetable Cycle name to accommodate more info

ALTER TABLE `sis_timetable_cycle`
    CHANGE COLUMN `title` `title` VARCHAR(255) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL ;

-- Add cycle_identifier to cycle

ALTER TABLE `sis_timetable_cycle`
    ADD COLUMN `cycle_identifier` VARCHAR(255) NULL AFTER `title`;

-- Change collation to whatever it was supposed to have been

ALTER TABLE `sis_timetable_cycle`
    CHANGE COLUMN `cycle_identifier` `cycle_identifier` VARCHAR(255) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL ;

-- Copy the timetable_campus values across

UPDATE sis_timetable_cycle
    INNER JOIN sis_timetable_campus ON sis_timetable_cycle.id = sis_timetable_campus.cycle_id
SET
    sis_timetable_cycle.start = sis_timetable_campus.start,
    sis_timetable_cycle.finish = sis_timetable_campus.finish,
    sis_timetable_cycle.start_day = sis_timetable_campus.start_day,
    sis_timetable_cycle.offset = sis_timetable_campus.offset,
    sis_timetable_cycle.reset = sis_timetable_campus.reset;

-- Assign luCampus.ext_timetable_code values to known cycles

UPDATE sis_timetable_cycle
    INNER JOIN sis_timetable_campus ON sis_timetable_cycle.id = sis_timetable_campus.cycle_id
    INNER JOIN luCampus ON sis_timetable_campus.campus_id = luCampus.id
SET sis_timetable_cycle.cycle_identifier = luCampus.ext_timetable_code;

-- Who knows what these three fields did...I certainly have no clue

ALTER TABLE `sis_timetable_cycle`
    DROP COLUMN `reset`,
    DROP COLUMN `regular`,
    DROP COLUMN `offset`;

-- Remove sis_timetable_campus altogether

DROP TABLE `sis_timetable_campus`;
