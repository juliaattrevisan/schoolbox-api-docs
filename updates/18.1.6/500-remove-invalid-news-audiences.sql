-- Delete all invalid audiences
DELETE FROM comms_news_audience
WHERE entity_type IN ('role', 'campus')
AND relationship != 'self';

-- Find articles without an audience, and set their status to 'DRAFT'.
-- see Schoolbox\Comms\News\Model::unSubmit()
UPDATE comms_news_article article
LEFT JOIN comms_news_audience audience ON audience.comms_news_article_id = article.id
SET
  article.submitted_at = NULL,
  article.accepted_at  = NULL,
  article.accepted_by  = NULL
WHERE audience.id IS NULL;

