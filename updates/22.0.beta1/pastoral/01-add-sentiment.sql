ALTER TABLE `lu_pastoral_type`
    ADD COLUMN `sentiment` ENUM ('positive', 'neutral', 'negative') NOT NULL DEFAULT 'neutral' AFTER `visibility_locked`;
ALTER TABLE `lu_pastoral_subtype`
    ADD COLUMN `sentiment` ENUM ('positive', 'neutral', 'negative') NOT NULL DEFAULT 'neutral' AFTER `visibility_locked`;
