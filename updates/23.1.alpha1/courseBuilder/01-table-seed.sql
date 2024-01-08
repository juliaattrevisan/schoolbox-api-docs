# create table
create table courseTemplates
(
    id               int(11) unsigned auto_increment,
    name             varchar(250) null,
    courseTemplateId int(11) unsigned not null,
    unitTemplateId   int(11) unsigned not null,
    deleted_at       datetime     null,
    constraint courseTemplates_pk
        primary key (id)
);

# allow homepage templates to be deleted and not break the whole system...
alter table homepageTemplate
    add deleted_at datetime null;

# ensure the course specs all have templates available
alter table course_specification
    add template_id int(11) unsigned null after subject_code;

alter table course_specification
    add constraint course_specification_courseTemplates_id_fk
        foreign key (template_id) references courseTemplates (id);

# copy config to new table
INSERT INTO courseTemplates (id, name, courseTemplateId, unitTemplateId) VALUE
(
    1,
    'Default',
    (SELECT value FROM config WHERE name='course_builder_default_course_page_template'),
    (SELECT value FROM config WHERE name='course_builder_default_unit_page_template')
);

# give all the course specs the default template
UPDATE course_specification SET course_specification.template_id = (SELECT id FROM courseTemplates WHERE name='Default') WHERE course_specification.template_id is null;
