alter table openai_log
    add input_tokens int null after prompt;

alter table openai_log
    add output_tokens int null after input_tokens;

alter table openai_log
    add model varchar(255) charset utf8mb4 null after output_tokens;

alter table openai_log
    add response mediumtext charset utf8mb4 null after model;

