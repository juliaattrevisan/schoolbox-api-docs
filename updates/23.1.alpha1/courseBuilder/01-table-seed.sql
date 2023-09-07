#create table
create table courseTemplates
(
    id               int(11) unsigned auto_increment,
    name             varchar(250) null,
    courseTemplateId int(11) unsigned not null,
    unitTemplateId   int(11) unsigned not null,
    deleted_at       datetime     null,
    constraint courseTemplates_pk
        primary key (id),
    constraint courseTemplates_homepageTemplate_id_fk
        foreign key (courseTemplateId) references homepageTemplate (id),
    constraint unitTemplates_homepageTemplate_id_fk2
        foreign key (unitTemplateId) references homepageTemplate (id)
);

# copy config to new table
INSERT INTO courseTemplates (name, courseTemplateId, unitTemplateId) VALUE
(
    'Default',
    (SELECT value FROM config WHERE name='course_builder_default_course_page_template'),
    (SELECT value FROM config WHERE name='course_builder_default_unit_page_template')
);
