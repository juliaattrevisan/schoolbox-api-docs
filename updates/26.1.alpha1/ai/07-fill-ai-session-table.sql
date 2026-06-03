-- For each set of openai_log rows with the same session_id:
--  * Take the session_id, minimum created_at value, and a value from any row for feature and user_id.
--  * It is okay to do this for feature and user_id because all rows in the set will have the same feature and user_id.
INSERT INTO ai_session (id, feature, created_at, created_by)
SELECT session_id, feature, MIN(created_at), user_id
FROM openai_log
GROUP BY session_id;
