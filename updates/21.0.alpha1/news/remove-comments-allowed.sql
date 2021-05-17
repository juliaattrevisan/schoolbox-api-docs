-- This removes comments_allowed, a nonfunctional db field and property
-- it doesn't actually do anything

ALTER TABLE `comms_news_article`
    DROP COLUMN `comments_allowed`;
