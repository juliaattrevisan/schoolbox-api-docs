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

### now for the initial data
INSERT INTO pronoun (subjective, objective, possessive, sequence, created_at, updated_at)
VALUES ('he', 'him', 'his', 2, now(), now()),
       ('she', 'her', 'hers', 3, now(), now()),
       ('they', 'them', 'theirs', 4, now(), now()),
       ('ze', 'hir', 'hirs', 5, now(), now());


### admin email signature tooltip
UPDATE config
SET description = "Email signatures will only be applied to users that have an email address that matches the School Email Domain.  Available tokens are: [title] [firstname] [surname] [fullname] [email] [position] [subjectivepronoun] [subjectivepronounproper] [objectivepronoun] [objectivepronounproper] [possessivepronoun] [possessivepronounproper]."
WHERE name = 'email_signature';

### admin full name formatter
UPDATE config
SET description = "Insert a default format for how user names will be displayed. Can contain: {title}, {firstname}, {preferredname} (falls back to first name if empty), {lastname} {subjectivepronoun} {subjectivepronounproper} {objectivepronoun} {objectivepronounproper} {possessivepronoun} {possessivepronounproper}."
WHERE name = 'name_format';
