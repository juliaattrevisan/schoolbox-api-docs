ALTER TABLE `menu_side_campus`
  CHANGE COLUMN `menu_side_id` `menu_side_id` INT(11) NOT NULL ,
  CHANGE COLUMN `campus_id` `campus_id` TINYINT(2) UNSIGNED NOT NULL ;

ALTER TABLE `menu_side_campus`
  ADD INDEX `fk_menu_side_campus_campus_id_idx` (`campus_id` ASC);
ALTER TABLE `menu_side_campus`
  ADD CONSTRAINT `fk_menu_side_campus_campus_id`
FOREIGN KEY (`campus_id`)
REFERENCES `luCampus` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `menu_side_role`
  CHANGE COLUMN `menu_side_id` `menu_side_id` INT(11) NOT NULL ,
  CHANGE COLUMN `role_id` `role_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `menu_side_role`
  ADD INDEX `fk_menu_side_role_role_id_idx` (`role_id` ASC);
ALTER TABLE `menu_side_role`
  ADD CONSTRAINT `fk_menu_side_role_role_id`
FOREIGN KEY (`role_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `menu_top_campus`
  CHANGE COLUMN `menu_top_id` `menu_top_id` INT(11) NOT NULL ,
  CHANGE COLUMN `campus_id` `campus_id` TINYINT(2) UNSIGNED NOT NULL ;


ALTER TABLE `menu_top_campus`
  ADD INDEX `fk_menu_top_campus_campus_id_idx` (`campus_id` ASC);
ALTER TABLE `menu_top_campus`
  ADD CONSTRAINT `fk_menu_top_campus_campus_id`
FOREIGN KEY (`campus_id`)
REFERENCES `luCampus` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `menu_top_role`
  CHANGE COLUMN `menu_top_id` `menu_top_id` INT(11) NOT NULL ,
  CHANGE COLUMN `role_id` `role_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `menu_top_role`
  ADD INDEX `fk_menu_top_role_role_id_idx` (`role_id` ASC);
ALTER TABLE `menu_top_role`
  ADD CONSTRAINT `fk_menu_top_role_role_id`
FOREIGN KEY (`role_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;