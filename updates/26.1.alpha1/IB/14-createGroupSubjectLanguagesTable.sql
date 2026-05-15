CREATE TABLE `ib_group_subject_languages`
(
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_subject_id` INT(11) NOT NULL,
    `language_id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ib_group_subject_languages_group_subject`
        FOREIGN KEY (`group_subject_id`) REFERENCES `ib_group_subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ib_group_subject_languages_language`
        FOREIGN KEY (`language_id`) REFERENCES `lu_language` (`id`),
    UNIQUE KEY `uniq_ib_group_subject_language` (`group_subject_id`, `language_id`),
    KEY `idx_ib_group_subject_languages_group_subject_id` (`group_subject_id`),
    KEY `idx_ib_group_subject_languages_language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert English translations for all existing group subjects
INSERT INTO `ib_group_subject_languages` (`group_subject_id`, `language_id`)
SELECT `ib_group_subjects`.`id`, `lu_language`.`id`
FROM `ib_group_subjects`
INNER JOIN `lu_language`
    ON `lu_language`.`code` = 'ENG';
