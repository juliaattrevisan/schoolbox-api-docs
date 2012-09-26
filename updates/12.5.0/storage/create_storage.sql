CREATE TABLE `storage`( `storage_id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `filename` VARCHAR(250) NOT NULL, `hash` VARCHAR(100) NOT NULL, `filesize` INT NOT NULL, `mime` VARCHAR(100), `uploaded_by` INT NOT NULL, `uploaded_on` DATETIME NOT NULL, `last_accessed` DATETIME, PRIMARY KEY (`storage_id`) );

ALTER TABLE `storage`
  ADD  UNIQUE INDEX `hash` (`hash`);