-- Drop the foreign key linking to openai_log.session_id here, to allow the
-- openai_log.session_id column to be changed in 03-backfill-openai-log-session-id.sql.
ALTER TABLE `dialog_agent_response`
    DROP FOREIGN KEY `fk_dar_session`;
