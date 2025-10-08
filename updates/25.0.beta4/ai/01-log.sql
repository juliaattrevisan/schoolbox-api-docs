alter table openai_log
    add input_tokens int null after prompt;

alter table openai_log
    add output_tokens int null after input_tokens;

alter table openai_log
    add model varchar(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' null after output_tokens;

alter table openai_log
    add response mediumtext CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' null after model;

