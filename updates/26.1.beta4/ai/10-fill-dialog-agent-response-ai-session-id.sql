UPDATE dialog_agent_response
SET ai_session_id = session_id
WHERE ai_session_id IS NULL
  AND session_id IS NOT NULL;
