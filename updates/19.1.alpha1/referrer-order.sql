-- Remove Impressions
-- They are no longer a concept
DELETE FROM comms_news_article_statistics WHERE `type` = 'impression';
DELETE FROM comms_news_article_statistics_cumulative WHERE `type` = 'impression';

-- Without Impressions, Conversions are conceptually the same as Opens
UPDATE comms_news_article_statistics SET `type` = 'open' WHERE `type` = 'conversion';
UPDATE comms_news_article_statistics_cumulative SET `type` = 'open' WHERE `type` = 'conversion';

-- Sort ascending, because ocd
ALTER TABLE `comms_news_article_statistics`
    CHANGE COLUMN `referrer` `referrer` ENUM('archive', 'dashboard', 'email', 'homepage', 'index', 'saved', 'search') CHARACTER SET 'ascii' NULL DEFAULT NULL ;

ALTER TABLE `comms_news_article_statistics_cumulative`
    CHANGE COLUMN `referrer` `referrer` ENUM('archive', 'dashboard', 'email', 'homepage', 'index', 'saved', 'search') CHARACTER SET 'ascii' NULL DEFAULT NULL ;

-- Interaction Type no longer includes Impressions or Conversions
ALTER TABLE `comms_news_article_statistics`
    CHANGE COLUMN `type` `type` ENUM('open') CHARACTER SET 'ascii' NULL DEFAULT NULL ;

ALTER TABLE `comms_news_article_statistics_cumulative`
    CHANGE COLUMN `type` `type` ENUM('open') CHARACTER SET 'ascii' NULL DEFAULT NULL ;
