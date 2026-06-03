-- Take the *first* log's feature/user info.
-- There won't be any sets of openai_log rows with same session_id and multiple features or user_ids at the moment.
INSERT INTO ai_session (id, feature, created_at, created_by)
SELECT session_id, feature, MIN(created_at), user_id
FROM openai_log
GROUP BY session_id;
