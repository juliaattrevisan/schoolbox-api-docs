-- Add session_id GUID field to openai_log
ALTER TABLE openai_log
    ADD COLUMN session_id CHAR(36) NULL AFTER user_id;