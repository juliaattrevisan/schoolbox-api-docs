-- The recreated v_rubric_mark view expects rubric_descriptor_mark table not to have any null descriptor_id.
DELETE FROM rubric_descriptor_mark WHERE descriptor_id IS NULL;
