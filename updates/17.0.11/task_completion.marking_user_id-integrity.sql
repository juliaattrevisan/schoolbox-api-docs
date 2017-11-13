-- If marking_user is null, reverse-engineer:
-- * if status = teacher assessed (3), conclude marked by teacher
-- * if status = self assessed (2), conclude marked by student
-- * if status = incomplete (1) and requires confirmation, conclude marked by teacher
-- * if status = incomplete (1) and self-assessable, conclude marked by student

-- Marked as teacher assessed => teacher
UPDATE task_completion tc
  JOIN task t ON t.id = tc.task_id
  SET tc.marking_user_id = t.user_id
  WHERE tc.marking_user_id IS NULL
  AND tc.completion_status = 3;

-- Marked as self assessed => student
UPDATE task_completion
  SET marking_user_id = student_id
  WHERE marking_user_id IS NULL
  AND completion_status = 2;

-- Marked as incomplete, and requires confirmation => teacher
UPDATE task_completion tc
  JOIN task t ON t.id = tc.task_id
  SET tc.marking_user_id = t.user_id
  WHERE tc.marking_user_id IS NULL
  AND tc.completion_status = 1
  AND t.requires_confirmation IS TRUE;

-- Marked as incomplete, and self-assessable => student
UPDATE task_completion tc
  JOIN task t ON t.id = tc.task_id
  SET tc.marking_user_id = tc.student_id
  WHERE tc.marking_user_id IS NULL
  AND tc.completion_status = 1
  AND t.requires_confirmation IS FALSE;


-- Change from optional to mandatory
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `task_completion`
  CHANGE `marking_user_id` `marking_user_id` INT(11) UNSIGNED NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;

