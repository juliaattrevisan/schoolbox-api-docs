-- I'm sick of enums for the config type, I'm replacing them with varchars
ALTER TABLE `config`
  CHANGE COLUMN `field` `field` VARCHAR(20) NULL DEFAULT 'input',
  CHANGE COLUMN `type`  `type`  VARCHAR(40) NULL DEFAULT NULL
;
