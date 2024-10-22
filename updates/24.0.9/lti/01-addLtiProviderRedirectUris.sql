create table lti_provider_redirect_uri
(
    id              int auto_increment primary key,
    lti_provider_id int           not null,
    redirect_uri    varchar(2048) not null,
    constraint lti_provider_redirect_uri_lti_provider_id_fk
        foreign key (lti_provider_id) references lti_provider (id)
);
