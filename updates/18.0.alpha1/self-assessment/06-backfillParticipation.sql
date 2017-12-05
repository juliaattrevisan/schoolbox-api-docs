-- Idempotence not required. (It's provided by the UNIQUE index across (plan_id, user_id)).

-- For each DueWork participant, find their earliest student_submission or submission return
INSERT INTO participation(participation_plan_id, user_id, created_at, updated_at)
  SELECT id, owner, MIN(created_at), MIN(created_at) FROM (
    SELECT * FROM (
      SELECT pp.id, ss.owner, ss.created_at
      FROM submission_box sb
      JOIN student_submission ss ON sb.id = ss.box_id
      JOIN participation_plan pp ON pp.assessment_id = sb.assessment_id
      WHERE sb.deleted IS FALSE
      AND ss.deleted_at IS NULL) t1
    UNION
    SELECT * FROM (
      SELECT pp.id, sr.owner, sr.created_at
      FROM submission_box sb
      JOIN submission_return sr ON sb.id = sr.box_id
      JOIN participation_plan pp ON pp.assessment_id = sb.assessment_id
      WHERE sb.deleted IS FALSE
      AND sr.deleted_at IS NULL) t2
  ) t3
  GROUP BY id, owner;

-- Find all quiz attempts and create a participation record for the student
INSERT INTO participation(participation_plan_id, user_id, created_at, updated_at)
  SELECT pp.id, qa.user_id, MIN(qa.start), MIN(qa.start)
  FROM assess q
  JOIN assess_attempt qa ON qa.assess_id = q.assess_id
  JOIN participation_plan pp ON pp.assessment_id = q.assessment_id
  WHERE q.deleted_at IS NULL
  AND qa.deleted_at IS NULL
  GROUP BY pp.id, qa.user_id;

-- Find all task completions and create a participation record for the student
INSERT INTO participation(participation_plan_id, user_id, created_at, updated_at)
  SELECT pp.id, tc.student_id, MIN(tc.completion_date), MIN(tc.completion_date)
  FROM task t
  JOIN task_completion tc ON tc.task_id = t.id
  JOIN participation_plan pp ON pp.assessment_id = t.assessment_id
  WHERE t.deleted_at IS NULL
  AND tc.deleted_at IS NULL
  GROUP BY pp.id, tc.student_id;
