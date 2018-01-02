ALTER TABLE `myFolder`
  CHANGE COLUMN `owner` `owner` INT(11) UNSIGNED NOT NULL DEFAULT '0' ;

DELETE myFolder FROM myFolder
  LEFT JOIN user ON user.id = owner
WHERE user.id IS NULL;


ALTER TABLE `myFolder`
  ADD CONSTRAINT `fk_myFolder_owner_id`
FOREIGN KEY (`owner`)
REFERENCES `user` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

