# Note NULL values are allowed in most string columns (other than ai_session_id) to match openai_log schema.
CREATE TABLE `ai_session_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ai_session_id` char(36) NOT NULL,
    `prompt` text NOT NULL,
    `input_tokens` int(11) DEFAULT NULL,
    `output_tokens` int(11) DEFAULT NULL,
    `input_text` text DEFAULT NULL,
    `output_text` text DEFAULT NULL,
    `model` varchar(255) DEFAULT NULL,
    `endpoint` varchar(255) DEFAULT NULL,
    `log_format` enum('agent_chat','guardrail') DEFAULT NULL,
    `response` mediumtext DEFAULT NULL,
    `return_code` int(11) DEFAULT NULL,
    `error_message` varchar(1024) DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `created_by` int(10) unsigned DEFAULT NULL COMMENT 'Null for system-created logs.',
    PRIMARY KEY (`id`),
    KEY `ai_session_log_ai_session_id_fk` (`ai_session_id`),
    KEY `ai_session_log_created_by_fk` (`created_by`),
    CONSTRAINT `ai_session_log_ai_session_id_fk` FOREIGN KEY (`ai_session_id`) REFERENCES `ai_session` (`id`),
    CONSTRAINT `ai_session_log_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
