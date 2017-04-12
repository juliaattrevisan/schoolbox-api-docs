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


ALTER TABLE `user`
  ADD INDEX `fk_user_residential_house_id_idx` (`residential_house_id` ASC);
ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_house_id`
FOREIGN KEY (`house`)
REFERENCES `house` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
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
