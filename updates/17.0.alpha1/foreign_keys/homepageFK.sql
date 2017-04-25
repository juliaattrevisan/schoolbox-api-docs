DELETE homepage_config FROM homepage_config
LEFT JOIN folder ON folder.id = cid
WHERE folder.id IS NULL;

ALTER TABLE `homepage_config`
  ADD CONSTRAINT `fk_homepage_config_folder_id`
FOREIGN KEY (`cid`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE homepage_visits FROM homepage_visits
  LEFT JOIN folder ON folder.id = category_id
WHERE folder.id IS NULL;

ALTER TABLE `homepage_visits`
  ADD INDEX `fk_homepage_visits_folder_id_idx` (`category_id` ASC);
ALTER TABLE `homepage_visits`
  ADD CONSTRAINT `fk_homepage_visits_folder_id`
FOREIGN KEY (`category_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE homepage_visits FROM homepage_visits
  LEFT JOIN groups ON groups.id = user_group_id
WHERE groups.id IS NULL;

ALTER TABLE `homepage_visits`
  ADD INDEX `fk_homepage_visits_role_id_idx` (`user_group_id` ASC);
ALTER TABLE `homepage_visits`
  ADD CONSTRAINT `fk_homepage_visits_role_id`
FOREIGN KEY (`user_group_id`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE userGroups FROM userGroups
  LEFT JOIN folder ON folder.id = cid
WHERE folder.id IS NULL;

ALTER TABLE `userGroups`
  CHANGE COLUMN `cid` `cid` INT(6) UNSIGNED NOT NULL DEFAULT '0' ;
ALTER TABLE `userGroups`
  ADD CONSTRAINT `fk_userGroups_folder_id`
FOREIGN KEY (`cid`)
REFERENCES `folder` (`parent`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
