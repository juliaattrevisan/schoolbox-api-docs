ALTER TABLE `sis_event`
    ADD COLUMN `metadata` JSON NULL DEFAULT NULL AFTER `type`;
