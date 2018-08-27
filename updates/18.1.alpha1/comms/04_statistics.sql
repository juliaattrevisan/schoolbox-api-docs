-- Itemised statistics per use
CREATE TABLE `comms_news_article_statistics` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `article_id` INT(11) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `type` ENUM('impression', 'conversion', 'open'),
  `referrer` ENUM('dashboard', 'homepage', 'index', 'archive', 'email') NULL,
  `role_type` ENUM('staff', 'parent', 'student', 'guest'),
  `occurs_at` DATETIME NOT NULL,
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

-- The (article_id, user_id, type, referrer) tuple must be unique for this period of reporting
-- We don't want people spamming this uncontrollably and skewing data
CREATE UNIQUE INDEX `idx_cnas_article_id_user_id_type_referrer`
ON `comms_news_article_statistics` (`article_id`, `user_id`, `type`, `referrer`, `role_type`) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;

-- Cumulative statistics per day, over all time
CREATE TABLE `comms_news_article_statistics_cumulative` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `article_id` INT(11) NOT NULL,
  `type` ENUM('impression', 'conversion', 'open'),
  `referrer` ENUM('dashboard', 'homepage', 'index', 'archive', 'email') NULL,
  `role_type` ENUM('staff', 'parent', 'student', 'guest'),
  `num` INT(11) DEFAULT 0 NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`));

-- The (date, article_id) tuple must be unique for this period of reporting
-- We don't want people spamming this uncontrollably and skewing data
-- We record cumulative totals per day, forever
CREATE UNIQUE INDEX `idx_cnasc_date_article_id`
ON `comms_news_article_statistics_cumulative` (`date`, `article_id`, `type`, `referrer`, `role_type`) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;

ALTER TABLE `comms_news_article_statistics_cumulative`
ADD CONSTRAINT `fk_comms_news_statistics_cumulative_article_id`
FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;
