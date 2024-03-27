# Allow work_type_id to be NULL to cater for assessments of type 'lesson plan'.
# Work type is not applicable to lesson plans.
# (This undoes a change that was made to make this column not nullable before the lesson plan problem was considered.)
ALTER TABLE assessment CHANGE COLUMN work_type_id work_type_id int(4) DEFAULT NULL;

# Insert a new 'None' row only if a row with name 'None' does not already exist.
INSERT INTO lu_work_type (
    name,
    external_result_type,
    show_on_grades_page,
    staff_or_instructor_visible_only,
    sequence,
    updated_by,
    created_at,
    updated_at,
    deleted_at
)
SELECT
'None',    # name,
NULL,      # external_result_type,
0,         # show_on_grades_page,
0,         # staff_or_instructor_visible_only,
0,         # sequence,
1,         # updated_by,
NOW(),     # created_at,
NOW(),     # updated_at,
NULL       # deleted_at
WHERE NOT EXISTS (
    SELECT *
    FROM lu_work_type
    WHERE name='None'
);

UPDATE assessment
SET work_type_id=(SELECT id FROM lu_work_type WHERE name='None')
WHERE work_type_id IS NULL;
