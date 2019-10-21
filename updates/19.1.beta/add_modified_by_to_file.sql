-- Add a modified by column which references who last modified the file
ALTER TABLE `file`    ADD COLUMN `modified_by` INT(4) UNSIGNED NULL DEFAULT NULL AFTER `submitted`;

-- Foreigner key
ALTER TABLE `file` ADD CONSTRAINT `fk_file_modified_by`
    FOREIGN KEY (`modified_by`)
        REFERENCES `user` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
