create table moment_evidence
(
    id        int auto_increment
        primary key,
    moment_id int unsigned                                                        not null,
    type      ENUM ('photo', 'text', 'upload', 'video', 'audio') default 'upload' not null,
    hash      VARCHAR(100) CHARSET ascii                                          null,
    filename  varchar(255)                                                        not null,
    created_at datetime                                                           not null,
    constraint moment_evidence_moment_id_fk
        foreign key (moment_id) references moment (id),
    constraint moment_evidence_storage_hash_fk
        foreign key (hash) references storage (hash)
);

create index moment_evidence_hash_index
    on moment_evidence (hash);

create index moment_evidence_moment_id_index
    on moment_evidence (moment_id);
