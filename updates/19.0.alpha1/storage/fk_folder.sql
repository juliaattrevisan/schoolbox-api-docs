ALTER TABLE `folder`
    ADD INDEX `fk_folder_image_hash_idx` (`image_hash` ASC);
ALTER TABLE `folder`
    ADD CONSTRAINT `fk_folder_image_hash`
        FOREIGN KEY (`image_hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
