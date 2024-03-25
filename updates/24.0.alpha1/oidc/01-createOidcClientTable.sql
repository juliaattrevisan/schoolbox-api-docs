create table oidc_client
(
    id                 int auto_increment,
    name               varchar(100) not null,
    client_id          varchar(100) not null,
    client_secret_hash varchar(100) not null,
    created_at         datetime     not null,
    updated_at         datetime     not null,
    deleted_at         datetime     not null,
    constraint oidc_client_pk
        primary key (id),
    constraint oidc_client_client_id
        unique (client_id),
    constraint oidc_client_name
        unique (name)
);
