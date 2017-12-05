-- Intentionally does not create plans for projects.
-- Project participants are the aggregate of their sub-assessment participants

-- Create a dynamic participation plan for each submission_box in the database
INSERT INTO participation_plan(assessment_id, is_dynamic, created_at, updated_at)
  SELECT a.id, 1, a.created_at, a.created_at
  FROM submission_box sb
  JOIN assessment a ON sb.assessment_id = a.id
  WHERE sb.deleted = 0
  AND a.deleted_at IS NULL;

-- Create a dynamic participation plan for each quiz in the database
INSERT INTO participation_plan(assessment_id, is_dynamic, created_at, updated_at)
  SELECT a.id, 1, a.created_at, a.created_at
  FROM assess q
  JOIN assessment a ON q.assessment_id = a.id
  WHERE q.deleted_at IS NULL
  AND a.deleted_at IS NULL;

-- Create a dynamic participation plan for each task in the database
INSERT INTO participation_plan(assessment_id, is_dynamic, created_at, updated_at)
  SELECT a.id, 1, a.created_at, a.created_at
  FROM task t
  JOIN assessment a ON t.assessment_id = a.id
  WHERE t.deleted_at IS NULL
  AND a.deleted_at IS NULL;
