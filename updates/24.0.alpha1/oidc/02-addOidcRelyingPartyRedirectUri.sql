/**
 * OIDC relying parties require a redirect URI.
 */
alter table oidc_relying_party
    add redirect_uri varchar(250) not null after client_secret_hash;
