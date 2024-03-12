INSERT INTO lu_work_type SET
name='None',
external_result_type=NULL,
show_on_grades_page=0,
staff_or_instructor_visible_only=0,
sequence=0,
updated_by=1,
created_at=NOW(),
updated_at=NOW(),
deleted_at=NULL;

UPDATE assessment
SET work_type_id=(SELECT id FROM lu_work_type WHERE name='None')
WHERE work_type_id IS NULL;

ALTER TABLE assessment CHANGE COLUMN work_type_id work_type_id int(4) NOT NULL;
