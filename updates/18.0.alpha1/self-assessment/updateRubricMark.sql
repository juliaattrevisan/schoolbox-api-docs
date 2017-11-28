# Currently rubric marks are related to students, rather than their submissions or feedbacks to them.
# To be able to distinguish whether a mark is given by a teacher or by a student for self-assessment,
# we need to link rubrick marks to returns (feedbacks) rather than students.

# Adding return_id column to the rubric_mark table and creating a foreign key for it
ALTER TABLE rubric_mark
  ADD return_id INT(11) UNSIGNED NULL DEFAULT NULL AFTER id,
  ADD CONSTRAINT `fk_rubric_mark_return_id`
  FOREIGN KEY (return_id)
  REFERENCES submission_return (id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;