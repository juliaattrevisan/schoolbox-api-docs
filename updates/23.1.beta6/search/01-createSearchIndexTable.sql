create table search_index
(
    identifier     varchar(64)   not null,
    title          varchar(1000) not null,
    type           varchar(32)   not null,
    date           datetime      null,
    relevance      float         not null,
    created_by     int unsigned  null,
    folder_id      int unsigned  null,
    fulltext_index mediumtext    not null,
    text_index     varchar(250)  not null,
    constraint search_index_pk
        primary key (identifier)
);

create fulltext index search_index_fulltext_index_index
    on search_index (fulltext_index);

create index search_index_text_index_index
    on search_index (text_index);

alter table search_index
    add constraint search_index_user_id_fk
        foreign key (created_by) references user (id)
            on update cascade on delete set null;

alter table search_index
    add constraint search_index_folder_id_fk
        foreign key (folder_id) references folder (id)
            on update cascade on delete set null;
