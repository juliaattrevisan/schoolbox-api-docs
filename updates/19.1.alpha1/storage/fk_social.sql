ALTER TABLE `socialstream_post`
    ADD CONSTRAINT `fk_socialstream_post_image_hash`
        FOREIGN KEY (`cache_hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;