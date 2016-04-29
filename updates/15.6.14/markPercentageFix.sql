UPDATE
  submission_return AS r
JOIN
  submission_box AS b ON r.box_id = b.id
SET
  mark = REPLACE(mark, '%%', '%')
WHERE
  mark_type = 1 AND mark LIKE '%\%\%%';
