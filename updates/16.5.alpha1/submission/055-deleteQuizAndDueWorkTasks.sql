/**
 * Tasks associated with due work and quiz were once deleted in 045-convertTasks.php.
 *
 * However, 050-convertTaskInstances.php needs the task ID in order to link
 * due work and quiz assessments.
 */
DELETE task FROM task
  LEFT JOIN assess ON task.id = assess.task_id
  LEFT JOIN submission_box ON task.id = submission_box.task_id
WHERE submission_box.id IS NOT NULL OR assess.assess_id IS NOT NULL

