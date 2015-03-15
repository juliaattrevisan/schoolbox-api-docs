-- Is this return a confirmation of a submission?
ALTER TABLE `submission_return`
    ADD COLUMN `is_confirmation_of_submission`
    BOOL NOT NULL DEFAULT FALSE
;
