create table pastoral_bulk_import
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
    constraint fk_pastoral_bulk_import_owner_id
        foreign key (owner_id) references user (id),
    constraint fk_pastoral_bulk_import_zip_hash
        foreign key (zip_hash) references storage (hash),
    constraint fk_pastoral_bulk_import_severity
        foreign key (severity_id) references lu_pastoral_severity (id),
    constraint fk_pastoral_bulk_import_type
        foreign key (type_id) references lu_pastoral_type (id),
    constraint fk_pastoral_bulk_import_subtype
        foreign key (subtype_id) references lu_pastoral_subtype (id)
);

create table pastoral_bulk_import_access
(
    id                      int auto_increment primary key,
    pastoral_bulk_import_id int                                          not null,
    group_id                int                                          null,
    role_id                 int unsigned                                 null,
    role_type               enum ('guest', 'student', 'parent', 'staff') null,
    user_id                 int unsigned                                 null,
    created_at              datetime                                     not null,
    deleted_at              datetime                                     null,
    access                  int     default 0                            null,
    match_campus            tinyint default 0                            null,
    comments_allowed        tinyint default 0                            null,
    constraint fk_pastoral_bulk_import_access_pastoral_bulk_import_id
        foreign key (pastoral_bulk_import_id) references pastoral (id),
    constraint fk_pastoral_bulk_import_access_group_id
        foreign key (group_id) references pastoral_group (id),
    constraint fk_pastoral_bulk_import_access_role_id
        foreign key (role_id) references `groups` (id),
    constraint fk_pastoral_bulk_import_user_id
        foreign key (user_id) references user (id)
);

create table pastoral_bulk_import_tags
(
    pastoral_bulk_import_id int not null,
    tag_id                  int not null,
    constraint unique_pastoral_bulk_import_tag_idx
        unique (pastoral_bulk_import_id, tag_id),
    constraint fk_pastoral_bulk_import_tags_pastoral
        foreign key (pastoral_bulk_import_id) references pastoral_bulk_import (id),
    constraint fk_pastoral_bulk_import_tags_tag
        foreign key (tag_id) references lu_pastoral_tag (id)
);
