-- Relationships between learning moments and folder(s)

create table moment_folder
(
    moment_id int unsigned not null,
    folder_id int unsigned not null,
    constraint moment_folder_folder_id_fk
        foreign key (folder_id) references folder (id),
    constraint moment_folder_moment_id_fk
        foreign key (moment_id) references moment (id)
);

create index moment_folder_moment_id_folder_id_index
    on moment_folder (moment_id, folder_id);
