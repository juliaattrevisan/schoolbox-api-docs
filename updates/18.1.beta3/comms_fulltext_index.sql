-- Fulltext search capability on title, blurb, body
CREATE FULLTEXT INDEX `idx_comms_news_article_fulltext`
  ON `comms_news_article` (title, body, blurb) COMMENT '' ALGORITHM DEFAULT LOCK DEFAULT;