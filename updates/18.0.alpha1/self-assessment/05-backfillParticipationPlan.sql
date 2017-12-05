-- Intentionally does not create plans for projects.
-- Project participants are the aggregate of their sub-assessment participants

-- Create a dynamic participation plan for each submission_box/quiz/task in the database
-- Do in a single query and order by created_at to simulate current behaviour
INSERT INTO participation_plan(assessment_id, is_dynamic, created_at, updated_at)
  SELECT * FROM (
    SELECT a.id, 1, a.created_at, a.created_at 'updated_at'
    FROM submission_box sb
    JOIN assessment a ON sb.assessment_id = a.id
    LEFT JOIN participation_plan pp ON a.id = pp.assessment_id
    WHERE sb.deleted = 0
    AND a.deleted_at IS NULL
    AND pp.id IS NULL
      UNION
    SELECT a.id, 1, a.created_at, a.created_at
    FROM assess q
    JOIN assessment a ON q.assessment_id = a.id
    LEFT JOIN participation_plan pp ON a.id = pp.assessment_id
    WHERE q.deleted_at IS NULL
    AND a.deleted_at IS NULL
    AND pp.id IS NULL
      UNION
    SELECT a.id, 1, a.created_at, a.created_at
    FROM task t
    JOIN assessment a ON t.assessment_id = a.id
    LEFT JOIN participation_plan pp ON a.id = pp.assessment_id
    WHERE t.deleted_at IS NULL
    AND a.deleted_at IS NULL
    AND pp.id IS NULL
    ) t1
  ORDER BY created_at;
