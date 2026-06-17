-- For each set of openai_log rows with the same session_id:
--  * Take the session_id, minimum created_at value, and minimum values also for both feature and user_id.
--  * It is okay to do this for feature and user_id because all rows in the set will have the same feature and user_id.
--  * Choosing feature and user without MIN() would fail on environments with ONLY_FULL_GROUP_BY enabled (according to Codex).
INSERT INTO ai_session (id, feature, created_at, created_by)
SELECT session_id, MIN(feature), MIN(created_at), MIN(user_id)
FROM openai_log
GROUP BY session_id;
