create table pastoral_bulk_upload
(
    id                 int auto_increment primary key,
    owner_id           int unsigned               not null,
    zip_hash           varchar(100) charset ascii not null,
    file_path_template varchar(255)               not null,
    severity_id        int                        null,
    type_id            int                        not null,
    subtype_id         int                        null,
    heading            varchar(255)               not null,
    body_public        mediumtext                 not null,
    points             float default 0            null,
    created_at         datetime                   not null,
    updated_at         datetime                   not null,
    deleted_at         datetime                   null,
    constraint fk_pastoral_bulk_upload_owner_id
        foreign key (owner_id) references user (id),
    constraint fk_pastoral_bulk_upload_zip_hash
        foreign key (zip_hash) references storage (hash),
    constraint fk_pastoral_bulk_upload_severity
        foreign key (severity_id) references lu_pastoral_severity (id),
    constraint fk_pastoral_bulk_upload_type
        foreign key (type_id) references lu_pastoral_type (id),
    constraint fk_pastoral_bulk_upload_subtype
        foreign key (subtype_id) references lu_pastoral_subtype (id)
);
