# Relax the unique index on the assessment in mv_assessment_relation:
# need to drop and restore its FK to do this
alter table mv_assessment_relation
    drop foreign key mv_assessment_relation_assessment_id_fk;

drop index assessment_id on mv_assessment_relation;

create index assessment_id
    on mv_assessment_relation (assessment_id);

alter table mv_assessment_relation
    add constraint mv_assessment_relation_assessment_id_fk
        foreign key (assessment_id) references assessment (id)
            on update cascade on delete cascade;

# Create a new unique index, this one encompassing all fields
create unique index mv_assessment_relation_uindex
    on mv_assessment_relation (
        assessment_id,
        folder_id,
        project_assessment_id,
        course_implementation_id,
        course_unit_implementation_id,
        course_specification_id,
        course_unit_specification_id
    );
