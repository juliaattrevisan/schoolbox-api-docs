-- Config type: update and replace with lookup table values
CREATE TABLE `lu_config_type` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(40),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `lu_config_type_type` (`type` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

-- Populate lookup table
INSERT INTO `lu_config_type` (`type`) SELECT DISTINCT `type` FROM `config` ORDER BY `type` DESC;

-- Alter config.type to varchar
ALTER TABLE `config` CHANGE COLUMN `type` `type` VARCHAR(250) NULL DEFAULT NULL;

-- Update config.type
UPDATE `config`
  INNER JOIN `lu_config_type` ON `config`.`type` = `lu_config_type`.`type`
    SET `config`.`type` = `lu_config_type`.`id`;

-- Alter config.type to int and hope everything casts properly?
ALTER TABLE `config` ADD COLUMN `type_id` INT NULL AFTER `type`;
UPDATE `config` SET `type_id` = CAST(`type` AS UNSIGNED);
ALTER TABLE `config` DROP COLUMN `type`;
