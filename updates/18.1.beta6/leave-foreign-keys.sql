ALTER TABLE `leave_request`
  CHANGE COLUMN `user_id` `user_id` INT(11) UNSIGNED NOT NULL ,
  CHANGE COLUMN `author_id` `author_id` INT(11) UNSIGNED NOT NULL ,
  CHANGE COLUMN `authorisation_user_id` `authorisation_user_id` INT(11) UNSIGNED NULL DEFAULT NULL ;

DELETE leave_request FROM leave_request
LEFT JOIN user ON user.id = leave_request.user_id
WHERE user.id IS NULL;

UPDATE leave_request
LEFT JOIN `user` ON leave_request.`author_id` = `user`.id
SET author_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

UPDATE leave_request
LEFT JOIN `user` ON leave_request.`authorisation_user_id` = `user`.id
SET authorisation_user_id = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null AND leave_request.authorisation_datetime IS NOT NULL;

UPDATE leave_request
LEFT JOIN `user` ON leave_request.`authorisation_user_id` = `user`.id
SET authorisation_user_id = NULL
WHERE `user`.id is null;


UPDATE leave_request
LEFT JOIN `lu_leave_request_travel_type` ON leave_request.`travel_type_id` = `lu_leave_request_travel_type`.id
SET travel_type_id = (SELECT `id` FROM `lu_leave_request_travel_type` LIMIT 1)
WHERE `lu_leave_request_travel_type`.id IS NULL
AND travel_type_id IS NOT NULL;

UPDATE leave_request
LEFT JOIN `lu_leave_request_host_type` ON leave_request.`host_type` = `lu_leave_request_host_type`.id
SET host_type = (SELECT `id` FROM `lu_leave_request_host_type` LIMIT 1)
WHERE `lu_leave_request_host_type`.id is null;


ALTER TABLE `leave_request`
  ADD INDEX `fk_leave_request_authorisation_user_id_idx` (`authorisation_user_id` ASC),
  ADD INDEX `fk_leave_request_host_type_id_idx` (`host_type` ASC);
ALTER TABLE `leave_request`
  ADD CONSTRAINT `fk_leave_request_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_leave_request_author_id`
FOREIGN KEY (`author_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_leave_request_authorisation_user_id`
FOREIGN KEY (`authorisation_user_id`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_leave_request_travel_type_id`
FOREIGN KEY (`travel_type_id`)
REFERENCES `lu_leave_request_travel_type` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_leave_request_host_type_id`
FOREIGN KEY (`host_type`)
REFERENCES `lu_leave_request_host_type` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
