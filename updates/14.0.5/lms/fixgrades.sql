/* Fix broken percentage data */
UPDATE submission_return r, submission_box b SET mark = CONCAT(mark,'%')
WHERE b.id = r.`box_id` AND mark_type=1 AND mark REGEXP '^[0-9]+$';

/* Fix broken total mark data */
UPDATE submission_return r, submission_box b SET mark = CONCAT(mark,' / ',`max_mark`)
WHERE b.id = r.`box_id` AND mark_type=3 AND mark REGEXP '^[0-9]+$';

/* Fix letter grades */
UPDATE submission_return r, submission_box b SET mark = (SELECT caption FROM submission_grade WHERE grade_id = mark)
WHERE b.id = r.`box_id` AND mark_type=2 AND mark REGEXP '^[0-9]+$';