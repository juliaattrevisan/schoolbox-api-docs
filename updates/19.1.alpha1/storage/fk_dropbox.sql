DELETE dropbox FROM dropbox LEFT JOIN storage ON storage.hash = dropbox.hash
WHERE storage.hash is null and dropbox.hash is not null;

ALTER TABLE `dropbox`
    ADD INDEX `fk_dropbox_storage_hash_idx` (`hash` ASC);
ALTER TABLE `dropbox`
    ADD CONSTRAINT `fk_dropbox_storage_hash`
        FOREIGN KEY (`hash`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
