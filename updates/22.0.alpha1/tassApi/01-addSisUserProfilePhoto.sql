ALTER TABLE sis_user_profile
    ADD COLUMN `portrait_hash` varchar(100) CHARACTER SET ascii DEFAULT NULL AFTER `lastname`;
