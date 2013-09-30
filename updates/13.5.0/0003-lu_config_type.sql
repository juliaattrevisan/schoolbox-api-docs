-- Config type: update and replace with lookup table values
CREATE TABLE `lu_config_type` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(40),
  PRIMARY KEY (`id`),
  UNIQUE INDEX `lu_config_type_type` (`type` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

-- Populate lookup table
INSERT INTO `lu_config_type` (`id`, `type`)
  VALUES
  (1,  'Site'),
  (2,  'Security'),
  (3,  'Paths'),
  (4,  'Resources'),
  (5,  'Policy'),
  (6,  'Localisation'),
  (7,  'External DB'),
  (8,  'Email'),
  (9,  'Colours'),
  (10, '3rd Party Services'),
  (11, 'Modules'),
  (12, 'Board Leave'),
  (13, 'Welcome')
;

-- Alter config.type to varchar
ALTER TABLE `config` CHANGE COLUMN `type` `type` VARCHAR(250) NULL DEFAULT NULL;

-- Update config.type
UPDATE `config`
  INNER JOIN `lu_config_type` ON `config`.`type` = `lu_config_type`.`type`
    SET `config`.`type` = `lu_config_type`.`id`;

-- Alter config.type to int and hope everything casts properly?
ALTER TABLE `config` ADD COLUMN `type_id` INT NULL AFTER `type`;
ALTER TABLE `config` ADD INDEX `config_type_id` (`type_id` ASC);
UPDATE `config` SET `type_id` = CAST(`type` AS UNSIGNED);
ALTER TABLE `config` DROP COLUMN `type`;
