-- Need to ensure data is clean before we try to apply FKs

-- Delete any orphan plagscans
DELETE plagscan_document FROM plagscan_document
LEFT JOIN `student_submission_file` ON student_submission_file.id = plagscan_document.student_submission_file_id
LEFT JOIN `student_submission`      ON student_submission.id      = student_submission_file.student_submission_id
LEFT JOIN `user`                    ON user.id                    = student_submission.owner
WHERE
  student_submission_file.id IS NULL OR
  student_submission.id      IS NULL OR
  user.id                    IS NULL;

-- Delete any orphan student submission files
DELETE student_submission_file FROM student_submission_file
  LEFT JOIN `student_submission`      ON student_submission.id      = student_submission_file.student_submission_id
  LEFT JOIN `user`                    ON user.id                    = student_submission.owner
WHERE
  student_submission.id      IS NULL OR
  user.id                    IS NULL;

-- Delete any orphan student submissions
DELETE student_submission FROM student_submission
  LEFT JOIN `user`                    ON user.id                    = student_submission.owner
WHERE
  user.id                    IS NULL;

-- Add the `owner` FK
ALTER TABLE student_submission
  ADD CONSTRAINT fk_student_submission_owner
FOREIGN KEY (owner)
REFERENCES user(id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- Insert new column before `owner` for semantic ordering: 'submitted_by' on behalf of 'owner'
ALTER TABLE student_submission
  ADD COLUMN submitted_by INT(4) UNSIGNED NOT NULL COMMENT 'User who performed create action' AFTER `id`;

-- Populate the new column - assume the submitter was the student - not much else we can do.
UPDATE student_submission
  SET submitted_by = owner;

-- Safely add the FK
ALTER TABLE student_submission
  ADD INDEX submitted_by (submitted_by),
  ADD CONSTRAINT fk_student_submission_submitted_by
    FOREIGN KEY (submitted_by)
    REFERENCES user(id)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION;
