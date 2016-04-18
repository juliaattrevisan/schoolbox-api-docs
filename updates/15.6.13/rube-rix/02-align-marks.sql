UPDATE rubric_mark rm
SET descriptor_id = (
  SELECT id
  FROM rubric_descriptor rd
  WHERE rd.criterion_id = rm.criterion_id
  AND rd.submission_box_id = rm.submission_box_id
  AND value = rm.mark
) WHERE mark IS NOT NULL
AND descriptor_id IS NULL;
