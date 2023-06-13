create table luPronoun
(
    id         int auto_increment,
    subjective varchar(250) null,
    objective  varchar(250) null,
    possessive varchar(250) null,
    sequence   int(11) unsigned                       not null,
    updated_by int(11) unsigned                       not null,
    created_at datetime default '1970-01-01 00:00:00' not null,
    updated_at datetime default '1970-01-01 00:00:00' not null,
    deleted_at datetime                               null,
    constraint luPronoun_pk
        primary key (id),
    constraint fk_luPronoun_user_id
        foreign key (updated_by) references user (id)
);

alter table user
    add pronoun int null after title;

alter table user
    add constraint fk_user_pronoun_id
        foreign key (pronoun) references luPronoun (id);

### now for the initial data
INSERT INTO luPronoun (subjective, objective, possessive, sequence, updated_by, created_at, updated_at)
VALUES (null, null, null, 1, 1, now(), now()),
       ('he', 'him', 'his', 2, 1, now(), now()),
       ('she', 'her', 'hers', 3, 1, now(), now()),
       ('they', 'them', 'theirs', 4, 1, now(), now()),
       ('ze', 'hir', 'hirs', 5, 1, now(), now());

