-- SA-12204
-- add better visibility around grade levels

alter table submission_grade_group
    add created_by int unsigned default null null;

alter table submission_grade_group
    add updated_by int unsigned default null null;

alter table submission_grade_group
    add created_at datetime not null;

alter table submission_grade_group
    add updated_at datetime not null;

alter table submission_grade_group
    add deleted_at datetime null;

create index fk_submission_grade_group_created_by_id_idx
	on submission_grade_group (created_by);

create index fk_submission_grade_group_updated_by_id_idx
	on submission_grade_group (updated_by);

alter table submission_grade_group
    add constraint fk_submission_grade_group_created_by_id
        foreign key (created_by) references user (id);

alter table submission_grade_group
    add constraint fk_submission_grade_group_updated_by_id
        foreign key (updated_by) references user (id);

