-- Config field: update and replace with lookup table values
CREATE TABLE `lu_config_field` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `field` VARCHAR(20),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `lu_config_field_field` (`field` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

-- Populate lookup table
INSERT INTO `lu_config_field` (`field`) SELECT DISTINCT `field` FROM `config` ORDER BY `field` DESC;

-- Alter config.field to varchar
ALTER TABLE `config` CHANGE COLUMN `field` `field` VARCHAR(250) NULL DEFAULT 'input';

-- Update config.field
UPDATE `config`
  INNER JOIN `lu_config_field` ON `config`.`field` = `lu_config_field`.`field`
    SET `config`.`field` = `lu_config_field`.`id`;

-- Alter config.field to int and hope everything casts properly?
ALTER TABLE `config` ADD COLUMN `field_id` INT NULL AFTER `field`;
ALTER TABLE `config` ADD INDEX `config_field_id` (`field_id` ASC);
UPDATE `config` SET `field_id` = CAST(`field` AS UNSIGNED);
ALTER TABLE `config` DROP COLUMN `field`;
