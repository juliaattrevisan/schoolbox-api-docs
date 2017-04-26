DELETE menu_side_campus FROM menu_side_campus
LEFT JOIN luCampus ON luCampus.id = menu_side_campus.campus_id
WHERE luCampus.id is null;

DELETE menu_side_campus FROM menu_side_campus
  LEFT JOIN menu_side ON menu_side.id = menu_side_campus.menu_side_id
WHERE menu_side.id is NULL;

DELETE menu_side_role FROM menu_side_role
  LEFT JOIN menu_side ON menu_side.id = menu_side_role.menu_side_id
WHERE menu_side.id is null;

DELETE menu_side_role FROM menu_side_role
  LEFT JOIN groups ON groups.id = menu_side_role.role_id
WHERE groups.id is null;

DELETE menu_top_campus FROM menu_top_campus
  LEFT JOIN luCampus ON luCampus.id = menu_top_campus.campus_id
WHERE luCampus.id is null;

DELETE menu_top_role FROM menu_top_role
  LEFT JOIN groups ON groups.id = menu_top_role.role_id
WHERE groups.id is null;

ALTER TABLE menu_side_campus
  DROP FOREIGN KEY menu_side_campus_menu_side_id_fk;

ALTER TABLE `menu_side_campus`
  CHANGE COLUMN `menu_side_id` `menu_side_id` INT(11) NOT NULL,
  CHANGE COLUMN `campus_id` `campus_id` TINYINT(2) UNSIGNED NOT NULL;

ALTER TABLE `menu_side_campus`
  ADD CONSTRAINT `menu_side_campus_menu_side_id_fk`
FOREIGN KEY (`menu_side_id`)
REFERENCES `menu_side` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `menu_side_campus`
  ADD INDEX `fk_menu_side_campus_campus_id_idx` (`campus_id` ASC);

ALTER TABLE `menu_side_campus`
  ADD CONSTRAINT `fk_menu_side_campus_campus_id`
FOREIGN KEY (`campus_id`)
REFERENCES `luCampus` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE menu_side_role
  DROP FOREIGN KEY menu_side_role_menu_side_id_fk;

ALTER TABLE `menu_side_role`
  CHANGE COLUMN `menu_side_id` `menu_side_id` INT(11) NOT NULL ,
  CHANGE COLUMN `role_id` `role_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `menu_side_role`
  ADD CONSTRAINT `menu_side_role_menu_side_id_fk`
FOREIGN KEY (`menu_side_id`)
REFERENCES `menu_side` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `menu_side_role`
  ADD INDEX `fk_menu_side_role_role_id_idx` (`role_id` ASC);
ALTER TABLE `menu_side_role`
  ADD CONSTRAINT `fk_menu_side_role_role_id`
FOREIGN KEY (`role_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE menu_top_campus
  DROP FOREIGN KEY menu_top_campus_menu_top_id_fk;

ALTER TABLE `menu_top_campus`
  CHANGE COLUMN `menu_top_id` `menu_top_id` INT(11) NOT NULL ,
  CHANGE COLUMN `campus_id` `campus_id` TINYINT(2) UNSIGNED NOT NULL ;

ALTER TABLE `menu_top_campus`
  ADD CONSTRAINT `menu_top_campus_menu_top_id_fk`
FOREIGN KEY (`menu_top_id`)
REFERENCES `menu_top` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `menu_top_campus`
  ADD INDEX `fk_menu_top_campus_campus_id_idx` (`campus_id` ASC);

ALTER TABLE `menu_top_campus`
  ADD CONSTRAINT `fk_menu_top_campus_campus_id`
FOREIGN KEY (`campus_id`)
REFERENCES `luCampus` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE menu_top_role
  DROP FOREIGN KEY menu_top_role_menu_top_id_fk;

ALTER TABLE `menu_top_role`
  CHANGE COLUMN `menu_top_id` `menu_top_id` INT(11) NOT NULL ,
  CHANGE COLUMN `role_id` `role_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `menu_top_role`
  ADD CONSTRAINT `menu_top_role_menu_top_id_fk`
FOREIGN KEY (`menu_top_id`)
REFERENCES `menu_top` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `menu_top_role`
  ADD INDEX `fk_menu_top_role_role_id_idx` (`role_id` ASC);

ALTER TABLE `menu_top_role`
  ADD CONSTRAINT `fk_menu_top_role_role_id`
FOREIGN KEY (`role_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

