ALTER TABLE `pastoral`
  CHANGE COLUMN `owner_id` `owner_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `student_id` `student_id` INT(11) UNSIGNED NOT NULL ;


ALTER TABLE `pastoral`
  ADD CONSTRAINT `fk_pastoral_student_id`
FOREIGN KEY (`student_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `pastoral`
  ADD INDEX `fk_pastoral_owner_id_idx` (`owner_id` ASC);
ALTER TABLE `pastoral`
  ADD CONSTRAINT `fk_pastoral_owner_id`
FOREIGN KEY (`owner_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `pastoral`
  ADD INDEX `fk_pastoral_category_id_idx` (`category_id` ASC);
ALTER TABLE `pastoral`
  ADD CONSTRAINT `fk_pastoral_category_id`
FOREIGN KEY (`category_id`)
REFERENCES `lu_pastoral_category` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
