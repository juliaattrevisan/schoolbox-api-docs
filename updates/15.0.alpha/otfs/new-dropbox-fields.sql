ALTER TABLE `dropbox`
  ADD COLUMN `deleted_at` DATETIME NULL AFTER `deleted`,
  ADD COLUMN `hash` VARCHAR(40) NOT NULL AFTER `deleted_at`;

UPDATE `dropbox`
  SET `deleted_at` = '1999-12-31 23:59:59'
  WHERE deleted = 1 AND deleted_at IS NULL;