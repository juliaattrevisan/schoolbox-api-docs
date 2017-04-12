ALTER TABLE `perms`
  CHANGE COLUMN `gid` `gid` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;

DELETE perms FROM perms
  LEFT JOIN `folder` ON cid = `folder`.id
WHERE `folder`.id is null;

DELETE perms FROM perms
  LEFT JOIN `groups` ON gid = `groups`.id
WHERE `groups`.id is null;


DELETE userCampus FROM userCampus
  LEFT JOIN `luCampus` ON campus = `luCampus`.id
WHERE `luCampus`.id is null;

ALTER TABLE `perms`
  ADD CONSTRAINT `fk_perms_folder_id`
FOREIGN KEY (`cid`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

ALTER TABLE `perms`
  ADD INDEX `fk_perms_role_id_idx` (`gid` ASC);
ALTER TABLE `perms`
  ADD CONSTRAINT `fk_perms_role_id`
FOREIGN KEY (`gid`)
REFERENCES `groups` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;