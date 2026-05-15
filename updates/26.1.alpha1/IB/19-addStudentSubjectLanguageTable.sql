CREATE TABLE `ib_registration_session_student_subject_language`
(
    `id`                              INT(11)  NOT NULL AUTO_INCREMENT,
    `registration_session_student_id` INT(11)  NOT NULL,
    `group_subject_id`                INT(11)  NOT NULL,
    `language_id`                     INT(11)  NOT NULL,
    `created_at`                      DATETIME NOT NULL DEFAULT current_timestamp(),
    `updated_at`                      DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ib_rss_subject_language_student_subject` (`registration_session_student_id`, `group_subject_id`),
    KEY `idx_ib_rss_subject_language_student_id` (`registration_session_student_id`),
    KEY `idx_ib_rss_subject_language_group_subject_id` (`group_subject_id`),
    KEY `idx_ib_rss_subject_language_language_id` (`language_id`),
    CONSTRAINT `fk_ib_rss_subject_language_student`
        FOREIGN KEY (`registration_session_student_id`) REFERENCES `ib_registration_session_student` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ib_rss_subject_language_group_subject`
        FOREIGN KEY (`group_subject_id`) REFERENCES `ib_group_subjects` (`id`),
    CONSTRAINT `fk_ib_rss_subject_language_language`
        FOREIGN KEY (`language_id`) REFERENCES `lu_language` (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
