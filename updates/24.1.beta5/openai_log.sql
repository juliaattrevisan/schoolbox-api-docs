create table openai_log
(
    id            int auto_increment
        primary key,
    user_id       INT unsigned not null,
    prompt        text     not null,
    return_code   int      null,
    error_message varchar(1024)  null,
    created_at    datetime not null,
    constraint openai_log_user_id_fk
        foreign key (user_id) references `user` (id)
);
