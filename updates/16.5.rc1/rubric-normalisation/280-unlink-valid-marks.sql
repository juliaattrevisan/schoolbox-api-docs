-- If a mark's descriptor does not exist, to enable FKs, we need to either:
-- * delete it, or
-- * set the descriptor link to null and permit nulls on the column.
-- To err on the side of caution, for now we will implement the latter.
ALTER TABLE `rubric_mark`
  MODIFY `descriptor_id` INT(11) UNSIGNED NULL;

-- Mark satisfies:
-- * mark.descriptor does not exist
-- * is valid mark
-- * mark.mark is NOT NULL
UPDATE rubric_mark rm
LEFT JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
SET rm.descriptor_id = NULL
WHERE rd.id IS NULL
AND rm.valid IS TRUE
AND rm.mark IS NOT NULL;