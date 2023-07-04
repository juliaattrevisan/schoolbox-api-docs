ALTER TABLE `pastoral_attachment`
    ADD COLUMN `external_id` INT DEFAULT NULL;
ALTER TABLE `pastoral_upload_queue`
    ADD COLUMN `pastoral_attachment_id` INT DEFAULT NULL;
ALTER TABLE `pastoral_upload_queue`
    ADD CONSTRAINT `fk_pastoral_attachment_id` FOREIGN KEY (`pastoral_attachment_id`)
        REFERENCES `pastoral_attachment` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;
