CREATE TABLE `ib_registration_sessions`
(
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `system_purpose_id` INT(11) NOT NULL,
    `academic_year` VARCHAR(4) NOT NULL,
    `exam_session` VARCHAR(10) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
    `created_by` INT(4) UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT current_timestamp(),
    `updated_by` INT(4) UNSIGNED NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` INT(4) UNSIGNED NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ib_registration_sessions_system_purpose_id` (`system_purpose_id`),
    KEY `idx_ib_registration_sessions_created_by` (`created_by`),
    KEY `idx_ib_registration_sessions_updated_by` (`updated_by`),
    KEY `idx_ib_registration_sessions_deleted_by` (`deleted_by`),
    CONSTRAINT `fk_ib_registration_sessions_system_purpose`
        FOREIGN KEY (`system_purpose_id`) REFERENCES `ib_system_purposes` (`id`),
    CONSTRAINT `fk_ib_registration_sessions_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `user` (`id`),
    CONSTRAINT `fk_ib_registration_sessions_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`),
    CONSTRAINT `fk_ib_registration_sessions_deleted_by`
        FOREIGN KEY (`deleted_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ib_registration_sessions` (`system_purpose_id`, `academic_year`, `exam_session`, `created_by`, `updated_by`)
VALUES (1, '2025', 'November', 1, 1),
       (1, '2026', 'May', 1, 1),
       (2, '2025', 'November', 1, 1),
       (3, '2025', 'May', 1, 1);

