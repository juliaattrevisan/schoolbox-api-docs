ALTER TABLE `resource`
    ADD CONSTRAINT `fk_resource_image_hash`
        FOREIGN KEY (`image_hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;