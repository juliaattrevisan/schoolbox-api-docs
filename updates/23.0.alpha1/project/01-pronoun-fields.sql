### demo and 23.0 instances they have already passed this point, but for old instances and sales (CI) we need to create these entries so the project cache will work
create table pronoun
(
    id         int auto_increment,
    subjective varchar(250) null,
    objective  varchar(250) null,
    possessive varchar(250) null,
    sequence   int(11) unsigned       not null,
    created_at datetime default now() not null,
    updated_at datetime default now() not null,
    deleted_at datetime               null,
    constraint pronoun_pk
        primary key (id)
);

alter table user
    add pronoun int null after title;

alter table user
    add constraint fk_user_pronoun_id
        foreign key (pronoun) references pronoun (id);
