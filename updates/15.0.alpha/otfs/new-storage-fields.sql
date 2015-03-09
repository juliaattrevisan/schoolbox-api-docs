ALTER TABLE `storage`
  ADD COLUMN `ext` VARCHAR(16) NOT NULL AFTER `hash`,
  ADD COLUMN `public` TINYINT(1) DEFAULT 0 NOT NULL AFTER `last_accessed`
  ADD INDEX (`ext`);

UPDATE `storage` SET ext =
  REVERSE(LEFT(REVERSE(filename), LOCATE('.', REVERSE(filename)) - 1));