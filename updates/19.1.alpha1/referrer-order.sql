-- Sort ascending, because ocd
ALTER TABLE `comms_news_article_statistics_cumulative`
    CHANGE COLUMN `referrer` `referrer` ENUM('archive', 'dashboard', 'email', 'homepage', 'index', 'saved', 'search') CHARACTER SET 'ascii' NULL DEFAULT NULL ;

-- Interaction Type no longer includes Impressions or Conversions
ALTER TABLE `comms_news_article_statistics_cumulative`
    CHANGE COLUMN `type` `type` ENUM('open') CHARACTER SET 'ascii' NULL DEFAULT NULL ;
