-- Create table and its indices
CREATE TABLE `assessment_relation` (
    `assessment_id` INT UNSIGNED NOT NULL UNIQUE,
    `folder_id` INT UNSIGNED NOT NULL,
    `project_assessment_id` INT UNSIGNED DEFAULT NULL,
    `course_implementation_id` INT UNSIGNED DEFAULT NULL,
    `course_unit_implementation_id` INT UNSIGNED DEFAULT NULL,
    `course_specification_id` INT UNSIGNED DEFAULT NULL,
    `course_unit_specification_id` INT UNSIGNED DEFAULT NULL
);
alter table assessment_relation
    add constraint mv_assessment_relation_assessment_id_fk
        foreign key (assessment_id) references assessment (id)
            on update cascade on delete cascade;

alter table assessment_relation
    add constraint mv_assessment_relation_course_implementation_id_fk
        foreign key (course_implementation_id) references course_implementation (id)
            on update cascade on delete cascade;

alter table assessment_relation
    add constraint mv_assessment_relation_course_specification_id_fk
        foreign key (course_specification_id) references course_specification (id)
            on update cascade on delete cascade;

alter table assessment_relation
    add constraint mv_assessment_relation_course_unit_implementation_id_fk
        foreign key (course_unit_implementation_id) references course_unit_implementation (id)
            on update cascade on delete cascade;

alter table assessment_relation
    add constraint mv_assessment_relation_course_unit_specification_id_fk
        foreign key (course_unit_specification_id) references course_unit_specification (id)
            on update cascade on delete cascade;

alter table assessment_relation
    add constraint mv_assessment_relation_folder_id_fk
        foreign key (folder_id) references folder (id)
            on update cascade on delete cascade;

alter table assessment_relation
    add constraint mv_assessment_relation_project_id_fk
        foreign key (project_assessment_id) references project (id)
            on update cascade on delete cascade;
