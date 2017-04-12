ALTER TABLE `schoolbox`.`user`
CHANGE COLUMN `firstname` `firstname` VARCHAR(255) NOT NULL DEFAULT '' ,
CHANGE COLUMN `lastname` `lastname` VARCHAR(255) NOT NULL DEFAULT '' ;
