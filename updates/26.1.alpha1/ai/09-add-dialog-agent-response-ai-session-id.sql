ALTER TABLE `dialog_agent_response`
    ADD COLUMN `ai_session_id` char(36) DEFAULT NULL AFTER `session_id`,
    ADD KEY `dialog_agent_response_ai_session_id_fk` (`ai_session_id`),
    ADD CONSTRAINT `dialog_agent_response_ai_session_id_fk`
        FOREIGN KEY (`ai_session_id`) REFERENCES `ai_session` (`id`);
