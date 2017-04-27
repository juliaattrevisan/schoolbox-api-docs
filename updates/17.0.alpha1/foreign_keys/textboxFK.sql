DELETE textBox FROM textBox
 LEFT JOIN folder ON folder.id = textBox.categoryID
WHERE folder.id IS NULL;

ALTER TABLE `textBox`
  CHANGE COLUMN `categoryID` `categoryID` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `textBox`
  ADD CONSTRAINT `fk_textBox_folder_id`
FOREIGN KEY (`categoryID`)
REFERENCES `folder` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;