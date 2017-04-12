DELETE userCampus FROM userCampus
  LEFT JOIN `user` ON uid = `user`.id
WHERE `user`.id is null;

ALTER TABLE `userCampus`
  ADD CONSTRAINT `fk_userCampus_user_id`
FOREIGN KEY (`uid`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE userCampus FROM userCampus
  LEFT JOIN `luCampus` ON campus = `luCampus`.id
WHERE `luCampus`.id is null;

ALTER TABLE `userCampus`
  ADD CONSTRAINT `fk_userCampus_campus_id`
FOREIGN KEY (`campus`)
REFERENCES `luCampus` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_role_id`
FOREIGN KEY (`group_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `house`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL ;
ALTER TABLE `luResidentialHouse`
  CHANGE COLUMN `id` `id` INT(20) UNSIGNED NOT NULL AUTO_INCREMENT ;


ALTER TABLE `user`
  CHANGE COLUMN `house` `house` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `residential_house_id` `residential_house_id` INT(11) UNSIGNED NULL DEFAULT NULL ;


ALTER TABLE `user`
  ADD INDEX `fk_user_residential_house_id_idx` (`residential_house_id` ASC);

ALTER TABLE `user`
  ADD INDEX `fk_user_house_id_idx` (`house` ASC);

UPDATE user LEFT JOIN house ON user.house = house.id SET user.house = null WHERE house.id is null and user.house is not null;

ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_house_id`
FOREIGN KEY (`house`)
REFERENCES `house` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE user LEFT JOIN luResidentialHouse ON user.residential_house_id = luResidentialHouse.id SET user.residential_house_id = null WHERE luResidentialHouse.id is null and user.residential_house_id is not null;

ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_residential_house_id`
FOREIGN KEY (`residential_house_id`)
REFERENCES `luResidentialHouse` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `superusers`
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;

ALTER TABLE `superusers`
  ADD CONSTRAINT `fk_superusers_user_id`
FOREIGN KEY (`id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `bookmarks`
  CHANGE COLUMN `owner` `owner` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;

DELETE bookmarks FROM bookmarks
  LEFT JOIN `user` ON `owner` = `user`.id
WHERE `user`.id is null;

ALTER TABLE `bookmarks`
  ADD CONSTRAINT `fk_bookmarks_user_id`
FOREIGN KEY (`owner`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


ALTER TABLE `user_link`
  ADD CONSTRAINT `fk_user_link_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_user_link_linked_user_id`
FOREIGN KEY (`linked_user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `user_password_reset_request`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `user_password_reset_request`
  ADD INDEX `fk_user_password_reset_request_user_id_idx` (`user_id` ASC);
ALTER TABLE `user_password_reset_request`
  ADD CONSTRAINT `fk_user_password_reset_request_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


