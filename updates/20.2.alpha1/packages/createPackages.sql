create table package
(
    id int unsigned auto_increment,
    name varchar(250) not null,
    description text null,
    installed bool null,
    constraint package_pk
        primary key (id)
)
    comment 'Contains packages that are available for install';

INSERT INTO package (name,description,installed) VALUES ('Australian Curriculum - General Capabilities', '', 0),
                                                        ('Australian Curriculum - Cross Curricular Priorities', '', 0),
                                                        ('Primary General Capability Badges', '', 0);
