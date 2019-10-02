-- Sort ascending, because ocd
ALTER TABLE `comms_news_article_statistics_cumulative`
    CHANGE COLUMN `referrer` `referrer` ENUM('archive', 'dashboard', 'email', 'homepage', 'index', 'saved', 'search') CHARACTER SET 'ascii' NULL DEFAULT NULL ;
