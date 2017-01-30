ALTER TABLE student_submission_file
    ADD INDEX uuid (uuid);

ALTER TABLE submission_return_file
  ADD INDEX uuid (uuid);