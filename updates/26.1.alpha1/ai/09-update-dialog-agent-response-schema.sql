ALTER TABLE dialog_agent_response
DROP FOREIGN KEY fk_dar_session,
CHANGE COLUMN session_id ai_session_id char(36) DEFAULT NULL,
ADD CONSTRAINT fk_dar_ai_session FOREIGN KEY (ai_session_id) REFERENCES ai_session (id);
