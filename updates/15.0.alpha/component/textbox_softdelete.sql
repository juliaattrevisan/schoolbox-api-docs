/* Give textboxes a softdeleted timestamp
 */
ALTER TABLE `textBox` CHANGE `deleted` `deleted` VARCHAR(255) NULL;
UPDATE `textBox` SET `deleted` = NOW() WHERE deleted = 'true';
UPDATE `textBox` SET `deleted` = NULL WHERE deleted = 'false';
ALTER TABLE `textBox` CHANGE `deleted` `deleted` DATETIME NULL;