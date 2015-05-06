ALTER TABLE `submission_errors`
    ADD COLUMN `is_default_for_new_submissions`
    BOOL NOT NULL DEFAULT FALSE
;
