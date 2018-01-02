DELETE eportfolio_component_filelist_item FROM eportfolio_component_filelist_item
  LEFT JOIN `eportfolio_page` ON eportfolio_component_filelist_item.page_id = `eportfolio_page`.page_id
WHERE `eportfolio_page`.page_id is null;

DELETE eportfolio_component_filelist_item FROM eportfolio_component_filelist_item
  LEFT JOIN `myFile` ON eportfolio_component_filelist_item.file_id = `myFile`.id
WHERE `myFile`.id is null;

ALTER TABLE `eportfolio_component_filelist_item`
  ADD CONSTRAINT `fk_eportfolio_component_filelist_page_id`
FOREIGN KEY (`page_id`)
REFERENCES `eportfolio_page` (`page_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_eportfolio_component_filelist_file_id`
FOREIGN KEY (`file_id`)
REFERENCES `myFile` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE myFile FROM myFile
  LEFT JOIN `storage` ON myFile.hash = `storage`.hash
WHERE `storage`.hash is null;

ALTER TABLE `myFile`
  ADD CONSTRAINT `fk_myFile_storage_hash`
FOREIGN KEY (`hash`)
REFERENCES `storage` (`hash`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

