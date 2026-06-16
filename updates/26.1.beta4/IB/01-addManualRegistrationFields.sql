ALTER TABLE `ib_registration_session_student`
    ADD COLUMN `manually_registered_by` INT(4) UNSIGNED NULL AFTER `subject_confirmed_by`,
    ADD COLUMN `manually_registered_at` DATETIME NULL AFTER `manually_registered_by`,
    ADD KEY `idx_ib_rss_manually_registered_by` (`manually_registered_by`),
    ADD CONSTRAINT `fk_ib_rss_manually_registered_by_user`
        FOREIGN KEY (`manually_registered_by`) REFERENCES `user` (`id`);
