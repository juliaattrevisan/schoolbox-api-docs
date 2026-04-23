ALTER TABLE sis_user_profile_student
    ADD nationalities VARCHAR(255) NULL AFTER photo_redacted,
    ADD languages VARCHAR(255) NULL AFTER nationalities;

