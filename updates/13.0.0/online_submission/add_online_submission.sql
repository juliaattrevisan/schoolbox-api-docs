ALTER TABLE `student_submission`
    ADD `body` TEXT,
    ADD `is_complete` BOOLEAN NOT NULL DEFAULT TRUE
;