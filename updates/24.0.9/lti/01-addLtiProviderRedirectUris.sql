create table lti_provider_redirect_url
(
    id              int auto_increment primary key,
    lti_provider_id int           not null,
    redirect_url    varchar(2048) not null,
    constraint lti_provider_redirect_url_lti_provider_id_fk
        foreign key (lti_provider_id) references lti_provider (id)
);
