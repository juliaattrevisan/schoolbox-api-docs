CREATE TABLE dialog_agent_response_message
(
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dialog_agent_response_id INT                         NOT NULL,
    role                     ENUM ('user', 'assistant')  NOT NULL,
    status                   ENUM ('ok', 'error')        NULL,
    content                  TEXT                        NULL,
    created_at               DATETIME                    NOT NULL,
    CONSTRAINT dialog_agent_response_message_dialog_agent_response_id_fk
        FOREIGN KEY (dialog_agent_response_id) REFERENCES schoolbox.dialog_agent_response (id)
);
