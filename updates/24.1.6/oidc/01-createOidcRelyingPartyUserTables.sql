-- Create tables for storing users which have granted access to an OIDC relying
-- party, and the scopes they have granted.
create table oidc_relying_party_grant
(
    id                    int unsigned auto_increment primary key,
    oidc_relying_party_id int unsigned not null,
    user_id               int unsigned not null,
    expires_at            datetime     not null,
    created_at            datetime     not null,
    updated_at            datetime     not null,
    deleted_at            datetime     null,
    constraint oidc_relying_party_grant_oidc_relying_party_id_fk
        foreign key (oidc_relying_party_id) references oidc_relying_party (id),
    constraint oidc_relying_party_grant_user_id_fk
        foreign key (user_id) references user (id)
);

create table oidc_relying_party_grant_scope
(
    id                          int unsigned auto_increment primary key,
    oidc_relying_party_grant_id int unsigned not null,
    scope                       varchar(64)  not null,
    constraint oidc_relying_party_grant_scope_oidc_relying_party_grant_id_fk
        foreign key (oidc_relying_party_grant_id) references oidc_relying_party_grant (id),
    unique index oidc_relying_party_grant_scope_uindex (oidc_relying_party_grant_id, scope)
);
