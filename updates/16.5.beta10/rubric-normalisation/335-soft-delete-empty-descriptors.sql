-- In the past, we hard-deleted descriptors if descriptor.value = NULL and descriptor.description = ''
-- But this had the effect of orphaning marks.

-- Now we soft-delete such descriptors.
UPDATE rubric_descriptor
SET deleted_at = NOW()
WHERE `value` IS NULL
AND `description` = '';
