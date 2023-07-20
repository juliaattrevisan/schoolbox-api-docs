ALTER TABLE `assess`
ADD COLUMN `learnosity_reference` VARCHAR(255) NULL AFTER `external_id`;

ALTER TABLE `assess_attempt`
    ADD COLUMN `learnosity_reference` VARCHAR(255) NULL AFTER `max_mark`;

-- Remember a percentage value so you don't have to
-- calculate it manually somewhere else
ALTER TABLE `assess_attempt`
    ADD COLUMN `norm_mark` DECIMAL(14,11) NULL AFTER `max_mark`;
