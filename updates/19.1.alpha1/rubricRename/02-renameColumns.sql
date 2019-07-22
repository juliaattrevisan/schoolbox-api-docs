alter table rubric_capability_mark  change rubric_group_id      rubric_capability_id      int(11) unsigned not null;
alter table rubric_capability_usage change rubric_group_id      rubric_capability_id      int(11) unsigned not null;
alter table rubric_descriptor_mark  change rubric_group_mark_id rubric_capability_mark_id int(11) unsigned not null;
alter table rubric_indicator        change rubric_group_id      rubric_capability_id      int(11) unsigned not null;
