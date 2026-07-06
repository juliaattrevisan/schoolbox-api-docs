CREATE TABLE `ib_registration_api_response_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `registration_session_id` INT(11) NOT NULL,
    `registration_session_student_id` INT(11) NULL,
    `msg_id` CHAR(96) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    `request_type` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) NOT NULL,
    `message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ib_registration_api_response_log_session_id` (`registration_session_id`),
    KEY `idx_ib_registration_api_response_log_student_id` (`registration_session_student_id`),
    KEY `idx_ib_registration_api_response_log_msg_id` (`msg_id`),
    KEY `idx_ib_registration_api_response_log_created_at` (`created_at`),
    CONSTRAINT `fk_ib_registration_api_response_log_session`
        FOREIGN KEY (`registration_session_id`) REFERENCES `ib_registration_sessions` (`id`),
    CONSTRAINT `fk_ib_registration_api_response_log_student`
        FOREIGN KEY (`registration_session_student_id`) REFERENCES `ib_registration_session_student` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
