UPDATE openai_log SET session_id = UUID_v4() WHERE session_id IS NULL;
ALTER TABLE openai_log CHANGE COLUMN session_id session_id char(36) NOT NULL;
