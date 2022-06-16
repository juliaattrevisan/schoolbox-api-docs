-- Have a look at all the turnitin_groups that were spawned
-- associated with a specification
-- (use this for manual inspections)
--
-- SELECT tg.*, '', sb.assessment_id, '', asi.*
-- FROM turnitin_group tg
-- INNER JOIN submission_box sb ON tg.submission_box_id = sb.id
-- INNER JOIN assessment a ON sb.assessment_id = a.id
-- INNER JOIN assessment_specification_implementation asi ON a.id = asi.specification_assessment_id;


-- soft-delete turnitin_group records that got stuck in
-- status "group" because they are actually for specifications
--
-- This  applies to any turnitin_group records that are for
-- assessment specifications, regardless of their status
UPDATE turnitin_group tg
INNER JOIN submission_box sb ON tg.submission_box_id = sb.id
INNER JOIN assessment a ON sb.assessment_id = a.id
INNER JOIN assessment_course_unit_specification acus ON a.id = acus.assessment_id
SET tg.deleted_at = NOW();