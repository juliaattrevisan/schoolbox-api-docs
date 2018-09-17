-- Don't even try to import rubric marks which don't belong to a return
DELETE FROM rubric_descriptor_mark WHERE return_id IS NULL;
