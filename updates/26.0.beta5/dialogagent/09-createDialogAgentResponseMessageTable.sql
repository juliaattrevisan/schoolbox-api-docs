CREATE TABLE dialog_agent_response_message
(
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dialog_agent_response_id INT                             NOT NULL,
    ai_response_id           VARCHAR(255)                    NULL,
    role                     ENUM ('user', 'assistant')      NOT NULL,
    status                   ENUM ('ok', 'pending', 'error') NOT NULL,
    content                  TEXT                            NULL,
    created_at               DATETIME                        NOT NULL,
    deleted_at               DATETIME                        NULL,
    CONSTRAINT dialog_agent_response_message_dialog_agent_response_id_fk
        FOREIGN KEY (dialog_agent_response_id) REFERENCES dialog_agent_response (id),
    INDEX dialog_agent_response_message_ai_response_id_index (ai_response_id)
);
