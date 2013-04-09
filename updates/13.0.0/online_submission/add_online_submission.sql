ALTER TABLE `student_submission`
    ADD `body` TEXT,
    ADD `draft` BOOLEAN NOT NULL DEFAULT FALSE
;