/**
 * Tasks associated with due work and quiz were once deleted in 045-convertTasks.php.
 *
 * However, 050-convertTaskInstances.php needs the task ID in order to link
 * due work and quiz assessments.
 */
DELETE FROM `task`
WHERE `assessment_id` IS NOT NULL
AND (`quiz_id` IS NOT NULL OR `assess_id` IS NOT NULL)
