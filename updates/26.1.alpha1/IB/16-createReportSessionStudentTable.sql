CREATE TABLE `ib_registration_session_student`
(
    `id`                      INT(11)         NOT NULL AUTO_INCREMENT,
    `registration_session_id` INT(11)         NOT NULL,
    `user_id`                 INT(4) UNSIGNED NOT NULL,
    `status`                  TINYINT(1)      UNSIGNED NOT NULL DEFAULT 0,
    `candidate_confirmed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `candidate_confirmed_at` DATETIME NULL,
    `candidate_confirmed_by` INT(4) UNSIGNED NULL,
    `subject_confirmed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `subject_confirmed_at` DATETIME NULL,
    `subject_confirmed_by` INT(4) UNSIGNED NULL,
    `created_at`              DATETIME        NOT NULL DEFAULT current_timestamp(),
    `updated_at`              DATETIME        NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_ib_registration_session_student_registration_session_id` (`registration_session_id`),
    UNIQUE KEY `uniq_ib_registration_session_student_id_user_id` (`registration_session_id`, `user_id`),
    KEY `idx_ib_rss_candidate_confirmed_by`
        (`candidate_confirmed_by`),
    KEY `idx_ib_rss_subject_confirmed_by`
        (`subject_confirmed_by`),
    CONSTRAINT `fk_ib_rss_registration_session`
        FOREIGN KEY (`registration_session_id`) REFERENCES `ib_registration_sessions` (`id`),
    CONSTRAINT `fk_ib_rss_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
    CONSTRAINT `fk_ib_rss_candidate_confirmed_updated_by_user`
        FOREIGN KEY (`candidate_confirmed_by`) REFERENCES `user` (`id`),
    CONSTRAINT `fk_ib_rss_subject_confirmed_updated_by_user`
        FOREIGN KEY (`subject_confirmed_by`) REFERENCES `user` (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
