UPDATE folder LEFT JOIN storage ON folder.image_hash = storage.hash SET folder.image_hash = NULL
WHERE storage.hash IS NULL AND folder.image_hash IS NOT NULL;

ALTER TABLE `folder`
    ADD INDEX `fk_folder_image_hash_idx` (`image_hash` ASC);
ALTER TABLE `folder`
    ADD CONSTRAINT `fk_folder_image_hash`
        FOREIGN KEY (`image_hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
