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

### Submission box missing foreign keys:
### * back up the previous column value
### * update the current column to a value guaranteed to match the FK value
### * apply the FK
alter table submission_box add column _rubric_capability_mark_type_id int(11) unsigned after rubric_capability_mark_type_id;
update submission_box set _rubric_capability_mark_type_id = rubric_capability_mark_type_id;
alter table submission_box modify column rubric_capability_mark_type_id int(11);
update submission_box sb
left join submission_mark_type smt on sb._rubric_capability_mark_type_id = smt.mark_type_id
set sb.rubric_capability_mark_type_id = smt.mark_type_id;
alter table submission_box add constraint fk_rubric_capability_mark_type foreign key (rubric_capability_mark_type_id)
    references submission_mark_type (mark_type_id);

alter table submission_box add column _rubric_capability_grade_group_id int(11) after rubric_capability_grade_group_id;
update submission_box set _rubric_capability_grade_group_id = rubric_capability_grade_group_id;
update submission_box sb
    left join submission_grade_group sgg on sb._rubric_capability_grade_group_id = sgg.id
set sb.rubric_capability_grade_group_id = sgg.id;
alter table submission_box add constraint fk_rubric_capability_grade_group foreign key (rubric_capability_grade_group_id)
    references submission_grade_group (id);

### Lesson plan assessment linking table
create unique index assessment_id_lesson_plan_id_index
    on lesson_plan_assessment (assessment_id, lesson_plan_id);

