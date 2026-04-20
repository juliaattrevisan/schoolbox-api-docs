CREATE TABLE `ib_group_subject_languages`
(
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_subject_id` INT(11) NOT NULL,
    `language_id` INT(11) NOT NULL,
    `class_code` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ib_group_subject_languages_group_subject`
        FOREIGN KEY (`group_subject_id`) REFERENCES `ib_group_subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ib_group_subject_languages_language`
        FOREIGN KEY (`language_id`) REFERENCES `lu_language` (`id`),
    UNIQUE KEY `uniq_ib_group_subject_language_class`
        (`group_subject_id`, `language_id`, `class_code`),
    KEY `idx_ib_group_subject_languages_group_subject_id` (`group_subject_id`),
    KEY `idx_ib_group_subject_languages_language_id` (`language_id`),
    KEY `idx_ib_group_subject_languages_class_code` (`class_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
