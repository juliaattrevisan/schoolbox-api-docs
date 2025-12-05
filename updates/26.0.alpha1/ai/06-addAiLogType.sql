-- Likely more to be added here as we add more ways to render logs,
-- I expect the next one would be some raw/plaintext option perhaps?
ALTER TABLE openai_log
    ADD log_type ENUM('agent_chat') NULL AFTER feature;
