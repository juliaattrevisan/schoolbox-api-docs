-- Delete marks if:
-- * mark.descriptor does not exist, or
-- * mark.student does not exist
-- Without a descriptor or student the record is useless
DELETE FROM rubric_mark WHERE id IN (
    SELECT * FROM (
      SELECT rm.id
      FROM rubric_mark rm
      LEFT JOIN rubric_descriptor rd ON rd.id = rm.descriptor_id
      LEFT JOIN user u ON u.id = rm.student_id
      WHERE (rd.id IS NULL OR u.id IS NULL)
    ) t
);
