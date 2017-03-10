-- Fix due dates for assessments within a unit implementation:
-- if it's at the start of the day, it should be the end of the day.
-- (If it's not, it's been manually fixed by a teacher.)
UPDATE assessment a
  INNER JOIN assessment_course_unit_implementation acui ON a.id=acui.assessment_id
SET a.due_date = CONCAT(DATE(a.due_date), ' 23:59:59')
WHERE TIME(a.due_date) = '00:00:00';

-- Fix late dates too
UPDATE assessment a
  INNER JOIN assessment_course_unit_implementation acui ON a.id=acui.assessment_id
SET a.close_date = CONCAT(DATE(a.close_date), ' 23:59:59')
WHERE TIME(a.close_date) = '00:00:00';

-- Fix dates for activities in projects inside unit implementations too
UPDATE assessment a
  INNER JOIN project_assessment                    prj_a ON a.id=prj_a.assessment_id
  INNER JOIN project                               prj   ON prj_a.project_id=prj.id
  INNER JOIN assessment                            a_prj ON prj.assessment_id = a_prj.id
  INNER JOIN assessment_course_unit_implementation acui  ON a_prj.id = acui.assessment_id
SET a.due_date = CONCAT(DATE(a.due_date), ' 23:59:59')
WHERE TIME(a.due_date) = '00:00:00';

UPDATE assessment a
  INNER JOIN project_assessment                    prj_a ON a.id=prj_a.assessment_id
  INNER JOIN project                               prj   ON prj_a.project_id=prj.id
  INNER JOIN assessment                            a_prj ON prj.assessment_id = a_prj.id
  INNER JOIN assessment_course_unit_implementation acui  ON a_prj.id=acui.assessment_id
SET a.close_date = CONCAT(DATE(a.close_date), ' 23:59:59')
WHERE TIME(a.close_date) = '00:00:00';
