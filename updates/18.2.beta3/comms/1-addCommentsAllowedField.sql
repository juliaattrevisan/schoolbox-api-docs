ALTER TABLE `comms_news_article`
ADD COLUMN `comments_allowed` BOOL NOT NULL DEFAULT FALSE AFTER `author_id`;
