/**
 * Add a user to impersonate when authorizing with client_credentials.
 */
alter table oidc_relying_party
    add client_credentials_user_id int unsigned null after redirect_uri;

alter table oidc_relying_party
    add constraint oidc_relying_party_user_id_fk
        foreign key (client_credentials_user_id) references user (id);
