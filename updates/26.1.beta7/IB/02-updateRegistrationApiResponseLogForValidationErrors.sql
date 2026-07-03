ALTER TABLE `ib_registration_api_response_log`
    MODIFY COLUMN `msg_id` CHAR(96) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
    ADD COLUMN `source` VARCHAR(30) NOT NULL DEFAULT 'ibis_api' AFTER `status`;
