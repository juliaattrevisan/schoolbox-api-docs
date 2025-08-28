create table moment_evidence
(
    moment_id int unsigned                NOT NULL,
    hash      VARCHAR(100) CHARSET ascii  NOT NULL,
    PRIMARY KEY (moment_id, hash),
    constraint moment_evidence_moment_id_fk
        foreign key (moment_id) references moment (id),
    constraint moment_evidence_hash_fk
        foreign key (hash) references storage (hash)
);
