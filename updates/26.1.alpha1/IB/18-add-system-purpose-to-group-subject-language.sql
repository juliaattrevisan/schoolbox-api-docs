-- Add system_purpose_id to ib_group_subject_languages so that Core subjects
-- (TOK, EE, CAS) can have independent language configurations per qualification
-- type (e.g. Diploma Programme Year 2 vs Diploma Course).
-- Non-Core subjects leave this column NULL.
--
-- The unique constraint on (group_subject_id, language_id) is widened to include
-- system_purpose_id so that the same language can be assigned to the same subject
-- under different qualification types without a duplicate-key error.

ALTER TABLE `ib_group_subject_languages`
    DROP INDEX `uniq_ib_group_subject_language`,
    ADD COLUMN `system_purpose_id` INT(11) DEFAULT NULL
        COMMENT 'For Core subjects only: references ib_system_purposes to allow per-qualification-type language configuration',
    ADD CONSTRAINT `fk_ib_gsl_system_purpose`
        FOREIGN KEY (`system_purpose_id`) REFERENCES `ib_system_purposes` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD UNIQUE KEY `uniq_ib_group_subject_language` (`group_subject_id`, `language_id`, `system_purpose_id`);
