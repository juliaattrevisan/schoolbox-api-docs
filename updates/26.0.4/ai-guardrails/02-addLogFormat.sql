ALTER TABLE `openai_log`
    MODIFY COLUMN `log_format` ENUM('agent_chat', 'guardrail') NULL;

ALTER TABLE `dialog_agent_response_message`
    MODIFY COLUMN `status` ENUM('ok', 'pending', 'error', 'guardrail_flagged') NOT NULL;
