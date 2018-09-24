-- News Policy: Global Moderation
-- When enabled, every news article requires moderation before it can reach the masses
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('global_moderation', 1);

-- News Policy: Publish Date Offset
-- When enabled, every article's publish date is offset by this amount
-- When empty, publish date is set to the current datetime for immediate effect
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('publish_date_offset', '');

-- News Policy: Expiry Date Offset
-- When enabled, every article's expiry date is offset by this amount
-- When empty, expiry date is set to the current datetime plus 1 week
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('expiry_date_offset', '+1 week');

-- News Policy: Read More
-- When provided, long articles are truncated and have a "read more" link appended
-- The content of the link is specified here
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('read_more', '');

-- Social Policy: Save For Later
-- When enabled, articles can be saved for later
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('save_for_later', 1);


-- News Policy: All Staff to Post News
-- When enabled, staff can post news to all audiences
-- When disabled, staff can only post news to groups they have write access to
INSERT INTO `comms_news_policy` (identifier, value)
VALUES ('news_staff_post', 1);


CREATE TABLE `comms_news_saveforlater` (
  `id`                 int(11)       PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `user_id`            int(11)       UNSIGNED NOT NULL,
  `article_id`         int(11)       NOT NULL,
  `created_at`         DATETIME      NOT NULL,
  `updated_at`         DATETIME      NOT NULL,
  `deleted_at`         DATETIME
);

ALTER TABLE `comms_news_saveforlater`
ADD CONSTRAINT `fk_comms_news_saveforlater_user_id`
FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `comms_news_saveforlater`
ADD CONSTRAINT `fk_comms_news_saveforlater_article_id`
FOREIGN KEY (`article_id`) REFERENCES `comms_news_article` (`id`)
ON DELETE NO ACTION ON UPDATE NO ACTION;

CREATE UNIQUE INDEX `idx_comms_news_saveforlater_user_id_article_id`
ON `comms_news_saveforlater` (user_id, article_id) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;