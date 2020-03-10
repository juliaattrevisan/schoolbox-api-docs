-- Add flag for Sticky News
ALTER TABLE `comms_news_article`
    ADD COLUMN `sticky` TINYINT(1) NOT NULL DEFAULT 0 AFTER `author_id`;
