-- There exist duplicate rubric_marks. In SEDA's data up to 11 duplicates, all with exactly the same fields.
-- This script gets all the duplicates, then delete's all but one instance

-- Create a temp table.
-- * 'first_mark_id' holds the first ID in a set of duplicates
-- * The other fields will later identify rows in sets of duplicates
CREATE TABLE temp_duplicate_marks (
  first_mark_id     INT(11) UNSIGNED,
  student_id        INT(11) UNSIGNED,
  submission_box_id INT(11) UNSIGNED,
  criterion_id      INT(11) UNSIGNED,
  descriptor_id     INT(11) UNSIGNED,
  tstamp            TIMESTAMP,
  mark              INT(11) UNSIGNED,
  valid             BOOL
);

INSERT INTO temp_duplicate_marks (
  SELECT *
  FROM rubric_mark
  GROUP BY student_id, submission_box_id, criterion_id, descriptor_id, tstamp, mark, valid
  HAVING count(*) > 1
);

DELETE FROM rubric_mark
WHERE id IN (
  SELECT * FROM (
    SELECT rm.id FROM rubric_mark rm
    JOIN temp_duplicate_marks tdm
      ON  tdm.student_id        = rm.student_id
      AND tdm.submission_box_id = rm.submission_box_id
      AND tdm.criterion_id      = rm.criterion_id
      AND tdm.descriptor_id     = rm.descriptor_id
      AND tdm.tstamp            = rm.tstamp
      AND tdm.mark              = rm.mark
      AND tdm.valid             = rm.valid
    WHERE tdm.first_mark_id != rm.id
  ) t
);

DROP TABLE temp_duplicate_marks;
