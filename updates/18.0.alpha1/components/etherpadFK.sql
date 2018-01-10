ALTER TABLE `etherpad_pad`
  CHANGE COLUMN `homepage_id` `homepage_id` INT(11) UNSIGNED NOT NULL COMMENT 'The homepage this pad started in' ;

DELETE etherpad_pad FROM etherpad_pad
  LEFT JOIN folder ON folder.id = etherpad_pad.homepage_id
WHERE folder.id IS NULL;

ALTER TABLE `etherpad_pad`
  ADD CONSTRAINT `fk_etherpad_pad_folder_id`
FOREIGN KEY (`homepage_id`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
