-- sis_class.code must be unique, to prevent dupe classes
-- from being inserted and manifesting in the timetable
ALTER TABLE `sis_class`
    DROP INDEX `code` ,
    ADD UNIQUE INDEX `code` (`code` ASC);

-- external ids should only be 64 chars long, as per user.synergy_id
ALTER TABLE `sis_class_period`
    CHANGE COLUMN `teacher_external_id` `teacher_external_id` VARCHAR(64) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL ;

ALTER TABLE `sis_class_user`
    CHANGE COLUMN `external_id` `external_id` VARCHAR(64) COLLATE 'utf8mb4_unicode_ci' NOT NULL ;

-- users can only have one instance of themselves in a class
ALTER TABLE `sis_class_user`
    ADD UNIQUE INDEX `class_user_involvement` (`class_id` ASC, `external_id` ASC);
