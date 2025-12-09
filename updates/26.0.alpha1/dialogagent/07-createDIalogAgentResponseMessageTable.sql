create table dialog_agent_response_message
(
    id                       int unsigned auto_increment primary key,
    dialog_agent_response_id int                         not null,
    role                     enum ('user', 'assistant')  not null,
    status                   int                         null,
    content                  text                        null,
    constraint dialog_agent_response_message_dialog_agent_response_id_fk
        foreign key (dialog_agent_response_id) references schoolbox.dialog_agent_response (id)
);
