create table search_index
(
    identifier     varchar(64)   not null,
    title          varchar(1000) not null,
    type           varchar(32)   not null,
    date           datetime      null,
    created_by     int unsigned  null,
    folder_id      int unsigned  null,
    constraint search_index_pk
        primary key (identifier)
);

alter table search_index
    add constraint search_index_user_id_fk
        foreign key (created_by) references user (id)
            on update cascade on delete set null;

alter table search_index
    add constraint search_index_folder_id_fk
        foreign key (folder_id) references folder (id)
            on update cascade on delete set null;
