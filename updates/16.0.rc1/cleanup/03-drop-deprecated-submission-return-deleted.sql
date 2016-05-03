ALTER TABLE `submission_return`
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL;

UPDATE `submission_return` SET `deleted_at` = `returned` WHERE `deleted` IS TRUE;

ALTER TABLE `submission_return`
  DROP COLUMN `deleted`;
