-- Add a relational table to describe the place from whence an
-- article has spawned; they can now come from a third party

CREATE TABLE `comms_news_article_source` (
  `article_id` INT NOT NULL,
  `source_name` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `guid` VARCHAR(255) CHARACTER SET 'ascii' NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  UNIQUE INDEX `article_id_UNIQUE` (`article_id` ASC),
  INDEX `source_name` (`source_name` ASC),
  INDEX `guid` (`guid` ASC))
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;