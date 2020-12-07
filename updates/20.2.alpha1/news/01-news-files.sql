-- News Articles can now be associated with multiple files

CREATE TABLE `comms_news_article_file` (
     `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
     `storage_id` VARCHAR(100) CHARACTER SET 'ascii' NULL DEFAULT NULL,
     `article_id` INT(11) NOT NULL,
     `realname` VARCHAR(255) NULL DEFAULT NULL,
     `created_at` DATETIME NOT NULL,
     `updated_at` DATETIME NOT NULL,
     `deleted_at` DATETIME NULL DEFAULT NULL,
     PRIMARY KEY (`id`));

-- Set up indexes

ALTER TABLE `comms_news_article_file`
    ADD INDEX `article_id` (`article_id` ASC),
    ADD INDEX `storage_id` (`storage_id` ASC);

-- Set up foreign keys

ALTER TABLE `comms_news_article_file`
    ADD CONSTRAINT `fk_comms_news_article_file`
        FOREIGN KEY (`article_id`)
            REFERENCES `comms_news_article` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article_file`
    ADD CONSTRAINT `fk_comms_news_article_file_storage`
        FOREIGN KEY (`storage_id`)
            REFERENCES `storage` (`hash`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;