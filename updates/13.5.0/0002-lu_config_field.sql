-- Config field and type: update and replace with lookup table values
CREATE TABLE `lu_config_field` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `field` VARCHAR(20),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `lu_config_field_field` (`field` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

-- Populate lookup table
INSERT INTO `lu_config_field` (`field`) SELECT DISTINCT `field` FROM `config`;

-- Alter config.field to varchar
ALTER TABLE `config` CHANGE COLUMN `field` `field` VARCHAR(250) NULL DEFAULT 'input';

-- Update config.field
UPDATE `config`
  INNER JOIN `lu_config_field` ON `config`.`field` = `lu_config_field`.`field`
    SET `config`.`field` = `lu_config_field`.`id`;

-- Alter config.field to int and hope everything casts properly?
ALTER TABLE `config` CHANGE COLUMN `field` `field` INT NULL;


CREATE TABLE `lu_config_type` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(40),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `lu_config_type_type` (`type` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;
