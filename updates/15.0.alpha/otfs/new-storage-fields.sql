ALTER TABLE `storage`
  ADD COLUMN `ext` VARCHAR(16) NOT NULL AFTER `filename`,
  ADD COLUMN `public` TINYINT(1) DEFAULT 0 NOT NULL AFTER `last_accessed`,
  ADD INDEX (`ext`);

/* We have to set public now or never, before all the mass-migrations fill the storage table with non-public records */
UPDATE `storage`
SET
  ext = REVERSE(LEFT(REVERSE(filename), LOCATE('.', REVERSE(filename)) - 1)),
  filename = REVERSE(RIGHT(REVERSE(filename), LENGTH(filename) - LOCATE('.', REVERSE(filename)))),
  `public` = 1;