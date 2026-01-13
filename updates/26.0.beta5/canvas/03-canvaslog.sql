create table canvas_import_log
(
    id            int auto_increment
        primary key,
    run_id        varchar(255)                          not null,
    script        varchar(255)                          not null,
    start_at      datetime                              not null,
    finish_at     datetime                              null,
    status        enum ('running', 'complete', 'error') not null,
    error_message text                                  null,
    created_at    datetime                              not null,
    updated_at    datetime                              not null
);

