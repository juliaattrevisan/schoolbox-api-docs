CREATE TABLE `comms_news_article_statistics` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `article_id` INT(11) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `type` ENUM('open', 'impression') NULL,
  `referrer` ENUM('dashboard', 'homepage', 'email') NULL,
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
