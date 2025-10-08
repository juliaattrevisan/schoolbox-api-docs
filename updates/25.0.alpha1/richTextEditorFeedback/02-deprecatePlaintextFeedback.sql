-- We're about to do a modification on all pre-existing feedback comments... so keep the old ones around for a release in case we need to revert
-- We don't need to do this for `quiz_feedback`, as that is already HTML and hence isn't being modified
ALTER TABLE `submission_return` RENAME COLUMN `comment` TO `_comment`;
ALTER TABLE `assess_attempt` RENAME COLUMN `comment` TO `_comment`;
ALTER TABLE `lti_assessment_score` RENAME COLUMN `comment` TO `_comment`;

ALTER TABLE `submission_return` ADD COLUMN `comment` MEDIUMTEXT CHARSET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT NULL AFTER `_comment`;
ALTER TABLE `assess_attempt` ADD COLUMN `comment` MEDIUMTEXT CHARSET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT NULL AFTER `_comment`;
ALTER TABLE `lti_assessment_score` ADD COLUMN `comment` MEDIUMTEXT CHARSET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' DEFAULT NULL AFTER `_comment`;
