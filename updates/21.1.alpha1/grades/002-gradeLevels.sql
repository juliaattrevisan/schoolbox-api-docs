-- SA-12204
-- add better visibility around grade levels

alter table submission_grade
    add created_by int unsigned default null null;

alter table submission_grade
    add updated_by int unsigned default null null;

alter table submission_grade
    add created_at datetime not null;

alter table submission_grade
    add updated_at datetime not null;

alter table submission_grade
    add deleted_at datetime null;

create index fk_submission_grade_created_by_id_idx
	on submission_grade (created_by);

create index fk_submission_grade_updated_by_id_idx
	on submission_grade (updated_by);

alter table submission_grade
    add constraint fk_submission_grade_created_by_id
        foreign key (created_by) references user (id);

alter table submission_grade
    add constraint fk_submission_grade_updated_by_id
        foreign key (updated_by) references user (id);

