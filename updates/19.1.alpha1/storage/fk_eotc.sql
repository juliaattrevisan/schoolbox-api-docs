DELETE eotc_metadata_file FROM eotc_metadata_file LEFT JOIN storage ON storage.storage_id = eotc_metadata_file.storage_id
WHERE storage.hash IS NULL;

ALTER TABLE `eotc_metadata_file`
    CHANGE COLUMN `storage_id` `storage_id` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `eotc_metadata_file`
    ADD CONSTRAINT `fk_eotc_metadata_file_storage`
        FOREIGN KEY (`storage_id`)
            REFERENCES `storage` (`storage_id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
