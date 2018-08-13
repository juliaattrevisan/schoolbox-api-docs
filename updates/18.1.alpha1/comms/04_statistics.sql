-- Itemised statistics per use
CREATE TABLE `comms_news_article_statistics` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `article_id` INT(11) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `type` ENUM('open', 'impression') NULL,
  `referrer` ENUM('dashboard', 'homepage', 'index', 'archive', 'email') NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`));

ALTER TABLE `comms_news_article_statistics`
ADD CONSTRAINT `fk_comms_news_statistics_article_id`
FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_article_statistics`
ADD CONSTRAINT `fk_comms_news_statistics_user_id`
FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

-- Cumulative statistics per day, over all time
CREATE TABLE `comms_news_article_statistics_cumulative` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `article_id` INT(11) NOT NULL,
  `first_open` DATETIME NULL,
  `num_impressions_dashboard` INT DEFAULT 0,
  `num_impressions_homepage` INT DEFAULT 0,
  `num_impressions_index` INT DEFAULT 0,
  `num_impressions_archive` INT DEFAULT 0,
  `num_impressions_email` INT DEFAULT 0,
  `num_conversions_dashboard` INT DEFAULT 0,
  `num_conversions_homepage` INT DEFAULT 0,
  `num_conversions_index` INT DEFAULT 0,
  `num_conversions_archive` INT DEFAULT 0,
  `num_conversions_email` INT DEFAULT 0,
  `num_opens` INT DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`));

CREATE UNIQUE INDEX `idx_comms_news_article_statistics_cumulative_date_article_id`
ON `comms_news_article_statistics_cumulative` (date, article_id) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;

ALTER TABLE `comms_news_article_statistics_cumulative`
ADD CONSTRAINT `fk_comms_news_statistics_cumulative_article_id`
FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;