ALTER TABLE `userAccess`
  CHANGE COLUMN `uid` `uid` INT(11) UNSIGNED NOT NULL DEFAULT '0' ,
  CHANGE COLUMN `service` `service` INT(11) UNSIGNED NOT NULL DEFAULT '0' ,
  CHANGE COLUMN `sid` `sid` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;

ALTER TABLE `luServices`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT , RENAME TO  `lu_service`;

DELETE userAccess FROM userAccess
  LEFT JOIN `user` ON uid = `user`.id
WHERE `user`.id is null;

ALTER TABLE `userAccess`
  ADD CONSTRAINT `fk_userAccess_user_id`
FOREIGN KEY (`uid`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `userAccess`
  ADD CONSTRAINT `fk_userAccess_service_id`
FOREIGN KEY (`service`)
REFERENCES `schoolbox`.`lu_service` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


