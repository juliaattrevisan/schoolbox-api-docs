/**
 * Create table for OIDC relying parties (a.k.a. clients).
 *
 * Note that `client_secret_hash` stores a hash of a generated but then
 * forgotten client secret: the client secret is never stored.
 */
create table oidc_relying_party
(
    id                 int unsigned auto_increment,
    name               varchar(100) not null,
    client_id          varchar(100) not null,
    client_secret_hash varchar(100) not null,
    created_at         datetime     not null,
    updated_at         datetime     not null,
    deleted_at         datetime     null,
    primary key (id),
    constraint oidc_client_client_id
        unique (client_id)
);
