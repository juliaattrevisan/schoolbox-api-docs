### Lesson plan assessment_id should be unique
alter table lesson_plan
    drop foreign key fk_lesson_plan_assessment;

drop index assessment_id on lesson_plan;

create unique index fk_lesson_plan_assessment
    on lesson_plan (assessment_id);

alter table lesson_plan
    add constraint fk_lesson_plan_assessment
        foreign key (assessment_id) references assessment (id);

### LTI assessment_id should be unique
alter table lti_assessment
    drop foreign key lti_assessment_assessment_id_fk;

drop index lti_assessment_assessment_id_fk on lti_assessment;

create unique index lti_assessment_assessment_id_fk
    on lti_assessment (assessment_id);

alter table lti_assessment
    add constraint lti_assessment_assessment_id_fk
        foreign key (assessment_id) references assessment (id);

### Submission box missing foreign keys
alter table submission_box modify column rubric_capability_mark_type_id int(11);
alter table submission_box add constraint fk_rubric_capability_mark_type foreign key (rubric_capability_mark_type_id)
    references submission_mark_type (mark_type_id);
alter table submission_box add constraint fk_rubric_capability_grade_group foreign key (rubric_capability_grade_group_id)
    references submission_grade_group (id);

### Lesson plan assessment linking table
create unique index assessment_id_lesson_plan_id_index
    on lesson_plan_assessment (assessment_id, lesson_plan_id);

