-- Add a rubric_instance_id column
alter table rubric_capability_usage
    add rubric_instance_id int not null after rubric_capability_id;

-- deprecate submission_box_id
alter table rubric_capability_usage
    change submission_box_id _submission_box_id int(11) unsigned not null;

