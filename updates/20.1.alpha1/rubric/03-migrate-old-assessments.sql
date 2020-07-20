-- Capability Mark Types inherit from the Assessment's Mark Type if the
-- Assessment's Mark Type was set to something other than None,
-- such as Total Mark, Letter Grade, Percentage
--
-- Capability Mark Types are given Total Mark if the Assessment's Mark Type
-- was set to None, emulating existing behaviour which relied on a temp hack

UPDATE submission_box
SET rubric_mark_per_capability = IF(mark_type =
                                    (SELECT mark_type_id FROM submission_mark_type WHERE slug = 'none'),
                                    (SELECT mark_type_id FROM submission_mark_type WHERE slug = 'raw'), mark_type),
    rubric_capability_grade_group_id = submission_grade_group_id
WHERE rubric_mark_per_capability = 1;

-- Otherwise, Mark Per Capability was off; that's synonymous with using TYPE_NONE as
-- the elected Capability Mark Type

UPDATE submission_box
SET rubric_mark_per_capability = (SELECT mark_type_id FROM submission_mark_type WHERE slug = 'none'),
    rubric_capability_grade_group_id = submission_grade_group_id
WHERE rubric_mark_per_capability = 0;
