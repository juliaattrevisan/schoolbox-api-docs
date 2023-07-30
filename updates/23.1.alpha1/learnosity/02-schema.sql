ALTER TABLE `assess`
ADD COLUMN `learnosity_reference` VARCHAR(255) NULL AFTER `external_id`;

ALTER TABLE `assess_attempt`
    ADD COLUMN `learnosity_reference` VARCHAR(255) NULL AFTER `max_mark`;
