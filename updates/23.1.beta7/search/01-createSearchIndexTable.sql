create table search_index
(
    id                         int auto_increment primary key,
    type                       varchar(32)   not null,
    identifier                 varchar(64)   not null,
    title                      varchar(1000) not null,
    date                       datetime      null,
    is_relevance_date_affected bool          not null,
    created_by                 int unsigned  null,
    fulltext_index             mediumtext    not null,
    text_index                 varchar(250)  not null
);

alter table search_index
    add constraint search_index_pk
        unique (type, identifier);

alter table search_index
    add constraint search_index_user_id_fk
        foreign key (created_by) references user (id)
            on update cascade on delete set null;

create fulltext index search_index_fulltext_index_index
    on search_index (fulltext_index);

create index search_index_text_index_index
    on search_index (text_index);
