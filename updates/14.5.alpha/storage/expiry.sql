/* add an optional expiry date */
ALTER TABLE `storage`
  ADD COLUMN `expired_at` DATETIME NULL AFTER `last_accessed`;
