ALTER TABLE sis_user_profile
    ADD COLUMN `portrait_hash` varchar(100) CHARACTER SET ascii DEFAULT NULL AFTER `lastname`;
-- Turn off photo_sis_import for TassApi, as this should be enabled after enabling the feature in TASS
UPDATE config
SET value =
        IF((SELECT value FROM config WHERE name = 'schooldata_import_data_source') = 'tassapi', 0, value)
WHERE name = 'photo_sis_import';
