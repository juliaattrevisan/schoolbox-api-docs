alter table comms_news_article_statistics modify referrer enum('archive', 'dashboard', 'email', 'homepage', 'index', 'rss', 'saved', 'search') charset ascii null;
alter table comms_news_article_statistics_cumulative modify referrer enum('archive', 'dashboard', 'email', 'homepage', 'index', 'rss', 'saved', 'search') charset ascii null;
