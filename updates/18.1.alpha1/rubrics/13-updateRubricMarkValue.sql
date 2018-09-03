UPDATE rubric_mark rm
INNER JOIN rubric_descriptor rd on rm.descriptor_id = rd.id
SET rm.value = rd.value;
