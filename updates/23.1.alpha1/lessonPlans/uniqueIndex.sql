### Lesson plan assessment_id should be unique
alter table lesson_plan
    drop foreign key fk_lesson_plan_assessment;

drop index assessment_id on lesson_plan;

create unique index assessment_id
    on lesson_plan (assessment_id);

alter table lesson_plan
    add constraint fk_lesson_plan_assessment
        foreign key (assessment_id) references assessment (id);

