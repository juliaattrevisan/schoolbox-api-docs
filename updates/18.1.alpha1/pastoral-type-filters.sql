ALTER TABLE `pastoral_report`
  ADD COLUMN `sub_types` TEXT NULL AFTER `types`,
  ADD COLUMN `houses` TEXT NULL AFTER `years`;