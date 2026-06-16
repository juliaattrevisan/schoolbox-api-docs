-- [SA-22508] Deprecate dialog_agent_response.session_id because it has been superseded by ai_session_id.
-- The ai_session_id column is a foreign key to ai_session.id.
-- The old session_id column is renamed instead of dropped so that data is retained for one release.
-- The old session_id FK was dropped (in script 01-drop-dialog-agent-response-session-fk.sql) before
-- openai_log.session_id was backfilled (in 03-backfill-openai-log-session-id.sql).
-- The remaining index is dropped here before session_id is renamed.
ALTER TABLE `dialog_agent_response`
    DROP INDEX `fk_dar_session_idx`,
    CHANGE COLUMN `session_id` `_session_id` CHAR(36) DEFAULT NULL;
