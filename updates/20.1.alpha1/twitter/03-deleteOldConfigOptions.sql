-- Remove old configs entirely
DELETE FROM config
WHERE name IN (
    'twitter_api_consumer_key',
    'twitter_api_consumer_secret',
    'twitter_api_oauth_token',
    'twitter_api_oauth_token_secret',
    'twitter_api_caching'
);
