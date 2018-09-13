-- Delete granular records that are older than 30 days
DELETE
FROM comms_news_article_statistics
WHERE occurs_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY occurs_at DESC