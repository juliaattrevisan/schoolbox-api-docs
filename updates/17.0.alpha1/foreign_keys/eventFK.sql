DELETE event_campus FROM event_campus
  LEFT JOIN `event` ON `event`.`id` = `event_campus`.event_id
WHERE `event`.id is null;

DELETE event_campus FROM event_campus
  LEFT JOIN `luCampus` ON `luCampus`.`id` = `event_campus`.campus_id
WHERE `luCampus`.id is null;

DELETE event_group FROM event_group
  LEFT JOIN `event` ON `event`.`id` = `event_group`.event_id
WHERE `event`.id is null;

DELETE event_group FROM event_group
  LEFT JOIN `groups` ON `groups`.`id` = `event_group`.group_id
WHERE `groups`.id is null;

DELETE event_category FROM event_category
  LEFT JOIN `event` ON `event`.`id` = `event_category`.event_id
WHERE `event`.id is null;

DELETE event_category FROM event_category
  LEFT JOIN `folder` ON `folder`.`id` = `event_category`.category_id
WHERE `folder`.id is null;

DELETE event_user FROM event_user
  LEFT JOIN `event` ON `event`.`id` = `event_user`.event_id
WHERE `event`.id is null;

DELETE event_user FROM event_user
  LEFT JOIN `user` ON `user`.`id` = `event_user`.user_id
WHERE `user`.id is null;

DELETE event_attendance FROM event_attendance
  LEFT JOIN event ON event.id = event_id
WHERE event.id IS NULL;

DELETE event_attendance FROM event_attendance
  LEFT JOIN user ON user.id = user_id
WHERE user.id IS NULL;

UPDATE event
  LEFT JOIN `user` ON `owner` = `user`.id
SET owner = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

UPDATE event
  LEFT JOIN `user` ON `author_id` = `user`.id
SET author_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `event`
  CHANGE COLUMN `id` `id` INT(6) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `series_id` `series_id` INT(6) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `owner` `owner` INT(6) UNSIGNED NOT NULL,
  CHANGE COLUMN `author_id` `author_id` INT(6) UNSIGNED NULL DEFAULT NULL ;


ALTER TABLE `event`
  ADD CONSTRAINT `fk_event_owner_id`
FOREIGN KEY (`owner`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `event`
  ADD CONSTRAINT `fk_event_author_id`
FOREIGN KEY (`author_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

UPDATE event
  LEFT JOIN `luEventType` ON luEventType.eventTypeID = event.type
SET event.type = (SELECT eventTypeID FROM luEventType LIMIT 1)
WHERE luEventType.eventTypeID is null;

ALTER TABLE `event`
  ADD INDEX `fk_event_type_id_idx` (`type` ASC);
ALTER TABLE `event`
  ADD CONSTRAINT `fk_event_type_id`
FOREIGN KEY (`type`)
REFERENCES `luEventType` (`eventTypeID`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `event_campus`
  CHANGE COLUMN `event_id` `event_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `campus_id` `campus_id` TINYINT UNSIGNED NOT NULL;

ALTER TABLE `event_campus`
  ADD CONSTRAINT `fk_event_campus_event_id`
FOREIGN KEY (`event_id`)
REFERENCES `event` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_event_campus_campus_id`
FOREIGN KEY (`campus_id`)
REFERENCES `luCampus` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;



ALTER TABLE `event_group`
  CHANGE COLUMN `event_id` `event_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `group_id` `group_id` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `event_group`
  ADD CONSTRAINT `fk_event_group_event_id`
FOREIGN KEY (`event_id`)
REFERENCES `event` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_event_group_role_id`
FOREIGN KEY (`group_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `event_category`
  CHANGE COLUMN `event_id` `event_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `category_id` `category_id` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `event_category`
  ADD CONSTRAINT `fk_event_category_event_id`
FOREIGN KEY (`event_id`)
REFERENCES `event` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_event_category_folder_id`
FOREIGN KEY (`category_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;




ALTER TABLE `event_user`
  CHANGE COLUMN `event_id` `event_id` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `event_user`
  ADD CONSTRAINT `fk_event_user_event_id`
FOREIGN KEY (`event_id`)
REFERENCES `event` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_event_user_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `event_attendance`
  CHANGE COLUMN `event_id` `event_id` INT(11) UNSIGNED NOT NULL ,
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `event_attendance`
  ADD CONSTRAINT `fk_event_attendance_event_id`
FOREIGN KEY (`event_id`)
REFERENCES `event` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_event_attendance_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

