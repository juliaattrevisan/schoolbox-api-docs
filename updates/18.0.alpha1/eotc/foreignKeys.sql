ALTER TABLE `eotc_event`
  CHANGE COLUMN `author_id` `author_id` INT(6) UNSIGNED NOT NULL ,
  CHANGE COLUMN `teacher_in_charge_id` `teacher_in_charge_id` INT(6) UNSIGNED NOT NULL ,
  CHANGE COLUMN `type` `type` INT(6) UNSIGNED NOT NULL,
  ADD INDEX `fk_eotc_event_type_idx` (`type` ASC);

UPDATE eotc_event
  LEFT JOIN `user` ON `author_id` = `user`.id
SET author_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

UPDATE eotc_event
  LEFT JOIN `user` ON `teacher_in_charge_id` = `user`.id
SET teacher_in_charge_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `eotc_event`
  ADD CONSTRAINT `fk_eotc_event_author_id`
FOREIGN KEY (`author_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_eotc_event_teacher_in_charge`
FOREIGN KEY (`teacher_in_charge_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_eotc_event_type`
FOREIGN KEY (`type`)
REFERENCES `luEventType` (`eventTypeID`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;