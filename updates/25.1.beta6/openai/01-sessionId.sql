-- Add sessionId GUID field to openai_log
ALTER TABLE openai_log
    ADD COLUMN sessionId CHAR(36) NULL AFTER user_id;