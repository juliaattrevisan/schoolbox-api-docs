-- This table tells you where an article originated;
-- used when importing from a third party soure

CREATE TABLE `comms_news_article_source` (
  `article_id` INT NOT NULL,
  `source_name` VARCHAR(255) NOT NULL,
  `guid` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  INDEX `article_id` (`article_id` ASC) VISIBLE,
  INDEX `source_name` (`source_name` ASC) VISIBLE,
  INDEX `source_guid` (`source_name` ASC, `guid` ASC) VISIBLE,
  CONSTRAINT `fk_article_id_comms_news_article_id`
    FOREIGN KEY (`article_id`)
    REFERENCES `comms_news_article` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION);
