create table search_index_folder
(
    search_index_id int not null,
    folder_id       int unsigned not null,
    constraint search_index_folder_search_index_id_fk
        foreign key (search_index_id) references search_index (id)
            on delete cascade on update cascade,
    constraint search_index_folder_folder_id_fk
        foreign key (folder_id) references folder (id)
            on delete cascade on update cascade
);
