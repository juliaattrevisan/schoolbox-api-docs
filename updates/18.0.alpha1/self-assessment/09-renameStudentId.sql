-- In the self-assessment we changed the logic, where the rubric marks are now linked to feedbacks (return_id), rather than to students
-- Thus the student_id column becomes deprecated
ALTER TABLE rubric_mark
  DROP FOREIGN KEY fk_rubric_mark_student_id,
  CHANGE COLUMN student_id _student_id INT(11) COMMENT 'deprecated in 18.0';