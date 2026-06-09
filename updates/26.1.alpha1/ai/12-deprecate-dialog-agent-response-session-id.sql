-- [SA-22508] Deprecate dialog_agent_response.session_id: superseded by
-- ai_session_id (FK to ai_session.id). Renamed (not dropped) for one release.
-- The FK constraint and index referencing session_id are dropped first (the
-- referenced table openai_log was renamed to _openai_log in script 11).
ALTER TABLE `dialog_agent_response`
    DROP FOREIGN KEY `fk_dar_session`,
    DROP INDEX `fk_dar_session_idx`,
    CHANGE COLUMN `session_id` `_session_id` CHAR(36) DEFAULT NULL;
