ALTER IGNORE TABLE `eportfolio_showcase_item`
  CHANGE COLUMN `uid` `uid` INT(11) UNSIGNED NOT NULL ,
  ADD INDEX `uid_idx` (`uid` ASC),
  ADD UNIQUE INDEX `showcase_unique_idx` (`submission_box_id` ASC, `uid` ASC)