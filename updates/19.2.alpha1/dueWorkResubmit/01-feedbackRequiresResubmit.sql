ALTER TABLE submission_return
    ADD resubmission_required BOOL DEFAULT FALSE NOT NULL
    AFTER norm_mark;
