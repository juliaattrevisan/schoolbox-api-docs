-- HTML comments previously made are squared away here, in case we needed it
ALTER TABLE `assess_attempt`
    ADD COLUMN `_comment` TEXT NULL AFTER `comment`;

-- Make a copy
UPDATE `assess_attempt`
SET `_comment` = `comment`;
