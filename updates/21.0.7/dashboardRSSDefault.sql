---update the default rss feed for the dashboard component to the current abc australia just-in feed
UPDATE component_config_definition t
SET t.default_value = 'https://www.abc.net.au/news/feed/51120/rss.xml'
WHERE t.id = 84;

