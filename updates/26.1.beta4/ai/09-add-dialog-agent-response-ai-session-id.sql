ALTER TABLE `dialog_agent_response`
    ADD COLUMN `ai_session_id` char(36) DEFAULT NULL AFTER `session_id`,
    ADD KEY `fk_dar_ai_session_idx` (`ai_session_id`),
    ADD CONSTRAINT `fk_dar_ai_session`
        FOREIGN KEY (`ai_session_id`) REFERENCES `ai_session` (`id`);
