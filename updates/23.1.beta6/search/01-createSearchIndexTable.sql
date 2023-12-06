create table search_index
(
    result_identifier     varchar(64)   not null,
    result_title          varchar(1000) not null,
    result_type           varchar(32)   not null,
    result_date           datetime      null,
    result_created_by     int unsigned  null,
    result_folder_id      int unsigned  null,
    result_fulltext_index mediumtext    not null,
    result_text_index     varchar(250)  null,
    constraint search_index_pk
        primary key (result_identifier)
);

create fulltext index search_index_result_fulltext_index_index
    on search_index (result_fulltext_index);

create index search_index_result_text_index_index
    on search_index (result_text_index);

alter table search_index
    add constraint search_index_user_id_fk
        foreign key (result_created_by) references user (id)
            on update cascade on delete set null;

alter table search_index
    add constraint search_index_folder_id_fk
        foreign key (result_folder_id) references folder (id)
            on update cascade on delete set null;
