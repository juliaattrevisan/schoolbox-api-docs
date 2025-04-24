-- We're about to do a modification on all pre-existing feedback comments... so keep the old ones around for a release in case we need to revert
ALTER TABLE `submission_return` ADD COLUMN `_comment` LONGTEXT DEFAULT NULL AFTER `comment`;
ALTER TABLE `assess_attempt` ADD COLUMN `_comment` LONGTEXT DEFAULT NULL AFTER `comment`;

UPDATE `submission_return` SET `_comment` = `comment`;
UPDATE `assess_attempt` SET `_comment` = `comment`;
