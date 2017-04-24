DROP TABLE wikiOpens;

DELETE wiki FROM wiki
LEFT JOIN folder ON categoryID = folder.id
WHERE folder.id IS NULL;

ALTER TABLE `schoolbox`.`wiki`
  CHANGE COLUMN `wikiID` `wikiID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `categoryID` `categoryID` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `latestRevision` `latestRevision` INT(11) UNSIGNED NOT NULL ;

ALTER TABLE `schoolbox`.`wiki`
  ADD CONSTRAINT `fk_wiki_folder_id`
FOREIGN KEY (`categoryID`)
REFERENCES `schoolbox`.`folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE wikiRevision FROM wikiRevision
  LEFT JOIN wiki ON wikiRevision.wikiID = wiki.wikiID
WHERE wiki.wikiID IS NULL;

UPDATE wikiRevision
  LEFT JOIN `user` ON `userID` = `user`.id
SET userID = (SELECT `id` FROM `user` WHERE username LIKE 'alaress')
WHERE `user`.id is null;

ALTER TABLE `schoolbox`.`wikiRevision`
  CHANGE COLUMN `revisionID` `revisionID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
  CHANGE COLUMN `wikiID` `wikiID` INT(11) UNSIGNED NULL DEFAULT NULL ,
  CHANGE COLUMN `userID` `userID` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `schoolbox`.`wikiRevision`
  ADD CONSTRAINT `fk_wikiRevision_wiki_id`
FOREIGN KEY (`wikiID`)
REFERENCES `schoolbox`.`wiki` (`wikiID`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_wikiRevision_user_id`
FOREIGN KEY (`userID`)
REFERENCES `schoolbox`.`user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;