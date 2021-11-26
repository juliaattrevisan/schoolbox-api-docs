-- Add student redaction for geosnap

ALTER TABLE `sis_user_profile_student`
ADD COLUMN `photo_redacted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `boarding_status`;
