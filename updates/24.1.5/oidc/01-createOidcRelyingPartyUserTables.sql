-- Create tables for storing users which have consented to share data with an
-- OIDC relying party, and the scopes they have consented to sharing.
create table oidc_relying_party_user
(
    id                    int unsigned auto_increment primary key,
    oidc_relying_party_id int unsigned not null,
    user_id               int unsigned not null,
    expires_at            datetime     not null,
    created_at            datetime     not null,
    updated_at            datetime     not null,
    deleted_at            datetime     null,
    constraint oidc_relying_party_user_oidc_relying_party_id_fk
        foreign key (oidc_relying_party_id) references oidc_relying_party (id),
    constraint oidc_relying_party_user_user_id_fk
        foreign key (user_id) references user (id)
);

create table oidc_relying_party_user_scope
(
    id                    int unsigned auto_increment primary key,
    oidc_relying_party_id int unsigned not null,
    scope                 varchar(64)  not null,
    constraint oidc_relying_party_user_scope_oidc_relying_party_user_id_fk
        foreign key (oidc_relying_party_id) references oidc_relying_party_user (id)
);

create unique index oidc_relying_party_user_scope_oidc_relying_party_id_scope_uindex
    on oidc_relying_party_user_scope (oidc_relying_party_id, scope);
