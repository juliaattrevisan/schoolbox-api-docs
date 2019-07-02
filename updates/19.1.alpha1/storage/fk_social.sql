UPDATE socialstream_post LEFT JOIN storage ON socialstream_post.cache_hash = storage.hash SET cache_hash = NULL
WHERE storage.hash IS NULL AND socialstream_post.cache_hash IS NOT NULL

ALTER TABLE `socialstream_post`
    ADD CONSTRAINT `fk_socialstream_post_image_hash`
        FOREIGN KEY (`cache_hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
