ALTER TABLE `openai_log`
    MODIFY COLUMN `log_format` ENUM('agent_chat', 'guardrail') NULL;
