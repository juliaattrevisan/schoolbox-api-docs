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

-- Test data for now

-- Seven impressions
INSERT INTO comms_news_article_statistics
(`article_id`, `user_id`, `type`, `referrer`, `created_at`, `updated_at`)
VALUES
(1, 1, 'impression', 'dashboard', '2018-08-10 14:08:00', '2018-08-10 14:08:00'),
(1, 6, 'impression', 'dashboard', '2018-08-10 14:08:11', '2018-08-10 14:08:11'),
(1, 22, 'impression', 'dashboard', '2018-08-10 15:10:46', '2018-08-10 15:10:46'),
(1, 24, 'impression', 'dashboard', '2018-08-10 15:21:03', '2018-08-10 15:21:03'),
(1, 25, 'impression', 'dashboard', '2018-08-10 15:21:06', '2018-08-10 15:21:06'),
(1, 26, 'impression', 'dashboard', '2018-08-10 15:22:33', '2018-08-10 15:22:33'),
(1, 27, 'impression', 'dashboard', '2018-08-10 15:22:49', '2018-08-10 15:22:49');

-- One conversion
INSERT INTO comms_news_article_statistics
(`article_id`, `user_id`, `type`, `referrer`, `created_at`, `updated_at`)
VALUES
(1, 24, 'open', 'dashboard', '2018-08-10 15:21:07', '2018-08-10 15:21:07');