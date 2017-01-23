ALTER TABLE `ups_effect`
  CHANGE COLUMN `arguments` `arguments` VARCHAR(4096) CHARACTER SET 'latin1' NOT NULL ;

ALTER TABLE `ups_condition`
  CHANGE COLUMN `arguments` `arguments` VARCHAR(4096) CHARACTER SET 'latin1' NOT NULL ;
