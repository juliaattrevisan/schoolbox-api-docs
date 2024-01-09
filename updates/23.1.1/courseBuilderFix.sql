alter table courseTemplates
    drop foreign key IF EXISTS courseTemplates_homepageTemplate_id_fk;

alter table courseTemplates
    drop foreign key IF EXISTS unitTemplates_homepageTemplate_id_fk2;

alter table courseTemplates
    modify courseTemplateId int not null;

alter table courseTemplates
    modify unitTemplateId int not null;

# if that homepage template doesn't exist (hard deleted), then use No Homepage as a default -1 and fix the broken schools
INSERT INTO courseTemplates (id, name, courseTemplateId, unitTemplateId) VALUE
    (
     1,
     'Default',
     (select COALESCE(
                     (SELECT value FROM config
                                            JOIN homepageTemplate on config.value = homepageTemplate.id
                      WHERE config.name = 'course_builder_default_course_page_template'),
                     -1)
     ),
     (select COALESCE(
                     (SELECT value FROM config
                                            JOIN homepageTemplate on config.value = homepageTemplate.id
                      WHERE config.name = 'course_builder_default_unit_page_template'),
                     -1)
     )
) ON DUPLICATE KEY UPDATE
    courseTemplateId = (select COALESCE(
                                       (SELECT value FROM config
                                                              JOIN homepageTemplate on config.value = homepageTemplate.id
                                        WHERE config.name = 'course_builder_default_course_page_template'),
                                       -1)
    ),
    unitTemplateId = (select COALESCE(
                                     (SELECT value FROM config
                                                            JOIN homepageTemplate on config.value = homepageTemplate.id
                                      WHERE config.name = 'course_builder_default_unit_page_template'),
                                     -1)
    );

# give all the course specs the default template, some schools may have broken on the above query, fix them here
UPDATE course_specification SET course_specification.template_id = (SELECT id FROM courseTemplates WHERE name='Default') WHERE course_specification.template_id is null;
