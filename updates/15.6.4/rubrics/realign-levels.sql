/* realigns levels on descriptors to match what they should be as per the submission box */
UPDATE rubric_level la
LEFT JOIN submission_box b ON la.box_id = b.id
LEFT JOIN rubric_descriptor d ON b.id = d.submission_box_id
RIGHT JOIN rubric_level lb ON d.level_id = lb.id AND la.sequence = lb.sequence
SET d.level_id = la.id
WHERE d.submission_box_id IN (
  SELECT DISTINCT submission_box_id
  FROM rubric_descriptor d
  LEFT JOIN rubric_level l ON d.level_id = l.id
  WHERE d.submission_box_id != l.box_id
);
