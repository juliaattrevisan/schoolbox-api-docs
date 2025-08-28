create table moment_evidence
(
    id        int auto_increment
        primary key,
    moment_id int unsigned                NOT NULL,
    hash      VARCHAR(100) CHARSET ascii  NOT NULL,
    filename  varchar(255)                NOT NULL,
    created_at datetime                   NOT NULL,
    constraint moment_evidence_moment_id_fk
        foreign key (moment_id) references moment (id),
    constraint moment_evidence_hash_fk
        foreign key (hash) references storage (hash)
);

create index moment_evidence_hash_index
    on moment_evidence (hash);

create index moment_evidence_moment_id_index
    on moment_evidence (moment_id);
