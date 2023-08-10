### Lesson plan assessment_id should be unique
alter table lesson_plan
    drop foreign key fk_lesson_plan_assessment;

drop index assessment_id on lesson_plan;

create unique index assessment_id
    on lesson_plan (assessment_id);

alter table lesson_plan
    add constraint fk_lesson_plan_assessment
        foreign key (assessment_id) references assessment (id);


alter table submission_box modify column rubric_capability_mark_type_id int(11);
alter table submission_box add constraint fk_rubric_capability_mark_type foreign key (rubric_capability_mark_type_id)
    references submission_mark_type (mark_type_id);
alter table submission_box add constraint fk_rubric_capability_grade_group foreign key (rubric_capability_grade_group_id)
    references submission_grade_group (id);
