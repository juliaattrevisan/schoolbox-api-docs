DELETE userAccess FROM userAccess
  LEFT JOIN `user` ON uid = `user`.id
WHERE `user`.id is null;

UPDATE userAccess uA
LEFT JOIN luServices luS ON luS.id = uA.service
SET service = 0
WHERE luS.id IS NULL;

ALTER TABLE `userAccess`
  CHANGE COLUMN `uid` `uid` INT(11) UNSIGNED NOT NULL,
  CHANGE COLUMN `service` `service` INT(11) UNSIGNED NOT NULL;

ALTER TABLE `luServices`
  RENAME TO  `lu_service`,
  CHANGE COLUMN `id` `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `userAccess`
  ADD CONSTRAINT `fk_userAccess_user_id`
FOREIGN KEY (`uid`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- THIS MUST BE THE LAST COMMAND IN THIS FILE
-- Not guaranteed to apply, as we have not cleaned invalid data.
-- We cannot clean invalid data because that invalid data may still work
-- because the codebase relies on hard-coded IDs having semantics.
ALTER TABLE `userAccess`
  ADD CONSTRAINT `fk_userAccess_service_id`
FOREIGN KEY (`service`)
REFERENCES `lu_service` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


