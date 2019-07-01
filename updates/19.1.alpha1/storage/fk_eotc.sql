ALTER TABLE `eotc_metadata_file`
    CHANGE COLUMN `storage_id` `storage_id` INT(11) UNSIGNED NULL DEFAULT NULL ;

ALTER TABLE `eotc_metadata_file`
    ADD CONSTRAINT `fk_eotc_metadata_file_storage`
        FOREIGN KEY (`storage_id`)
            REFERENCES `storage` (`storage_id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;


