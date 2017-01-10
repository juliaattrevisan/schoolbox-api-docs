-- Repair created and updated dates for student submission files
UPDATE student_submission_file ssf
INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
SET `ssf`.`created_at` = `ss`.`created_at`
WHERE `ssf`.`created_at` = '0000-00-00 00:00:00';

UPDATE student_submission_file ssf
INNER JOIN student_submission ss ON ssf.student_submission_id = ss.id
SET `ssf`.`updated_at` = `ss`.`updated_at`
WHERE `ssf`.`updated_at` = '0000-00-00 00:00:00';
