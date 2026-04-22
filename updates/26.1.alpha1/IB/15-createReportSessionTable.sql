CREATE TABLE `ib_registration_sessions`
(
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `system_purpose_id` INT(11) NOT NULL,
    `created_at` DATETIME DEFAULT NOW() NOT NULL,
    `updated_at` DATETIME DEFAULT NOW() NULL,
    `deleted_at` DATETIME DEFAULT NULL NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ib_registration_sessions_system_purpose`
        FOREIGN KEY (`system_purpose_id`) REFERENCES `ib_system_purposes` (`id`),
    KEY `idx_ib_registration_sessions_system_purpose_id` (`system_purpose_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
