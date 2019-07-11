UPDATE resource LEFT JOIN storage ON resource.image_hash = storage.hash SET image_hash = NULL
WHERE  storage.hash IS NULL AND resource.image_hash IS NOT NULL;

ALTER TABLE `resource`
    ADD CONSTRAINT `fk_resource_image_hash`
        FOREIGN KEY (`image_hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
