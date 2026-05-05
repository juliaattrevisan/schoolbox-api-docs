CREATE TABLE `ib_group_subject_language_class_codes`
(
    `group_subject_language_id` INT(11) NOT NULL,
    `class_code` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`group_subject_language_id`, `class_code`),
    CONSTRAINT `fk_ib_group_subject_language_class_codes_language`
        FOREIGN KEY (`group_subject_language_id`) REFERENCES `ib_group_subject_languages` (`id`) ON DELETE CASCADE,
    KEY `idx_ib_group_subject_language_class_codes_class_code` (`class_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- INSERT IGNORE INTO `ib_group_subject_language_class_codes`
--     (`group_subject_language_id`, `class_code`)
-- SELECT keeper.`id`, languages.`class_code`
-- FROM `ib_group_subject_languages` languages
-- INNER JOIN (
--     SELECT `group_subject_id`, `language_id`, MIN(`id`) AS `id`
--     FROM `ib_group_subject_languages`
--     GROUP BY `group_subject_id`, `language_id`
-- ) keeper
--     ON keeper.`group_subject_id` = languages.`group_subject_id`
--     AND keeper.`language_id` = languages.`language_id`
-- WHERE languages.`class_code` <> '';
--
-- DELETE languages
-- FROM `ib_group_subject_languages` languages
-- INNER JOIN (
--     SELECT `group_subject_id`, `language_id`, MIN(`id`) AS `id`
--     FROM `ib_group_subject_languages`
--     GROUP BY `group_subject_id`, `language_id`
-- ) keeper
--     ON keeper.`group_subject_id` = languages.`group_subject_id`
--     AND keeper.`language_id` = languages.`language_id`
-- WHERE languages.`id` <> keeper.`id`;

-- ALTER TABLE `ib_group_subject_languages`
--     DROP INDEX `uniq_ib_group_subject_language_class`,
--     DROP INDEX `idx_ib_group_subject_languages_class_code`,
--     DROP COLUMN `class_code`,
--     ADD UNIQUE KEY `uniq_ib_group_subject_language` (`group_subject_id`, `language_id`);
