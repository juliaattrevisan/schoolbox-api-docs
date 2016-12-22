ALTER TABLE `submission_return_file`
  ADD FOREIGN KEY (`submission_return_id`) REFERENCES `submission_return`(`id`);

ALTER TABLE `submission_return`
  ADD INDEX (`student_submission_id`);

ALTER TABLE `task_completion`
  CHANGE `student_id` `student_id` INT(11) UNSIGNED NOT NULL,
  CHANGE `marking_user_id` `marking_user_id` INT(11) UNSIGNED NULL,
  ADD FOREIGN KEY (`student_id`) REFERENCES `user`(`id`),
  ADD FOREIGN KEY (`marking_user_id`) REFERENCES `user`(`id`);

ALTER TABLE `task_completion`
  ADD INDEX (`deleted_at`);

ALTER TABLE `assessment`
  ADD INDEX (`deleted_at`);

ALTER TABLE `user`
  ADD INDEX (`deleted_at`);

ALTER TABLE `assess_attempt`
  ADD INDEX (`reviewed_at`);

ALTER TABLE `student_submission`
  ADD INDEX (`deleted_at`);

ALTER TABLE `student_submission_file`
  ADD INDEX (`deleted_at`);

ALTER TABLE `submission_return`
  ADD INDEX (`deleted_at`);

ALTER TABLE `submission_return_file`
  ADD INDEX (`deleted_at`);

ALTER TABLE `course_unit_implementation`
  ADD INDEX (`course_implementation_id`);

ALTER TABLE `submission_return_file`
  ADD INDEX (`submission_return_id`);

ALTER TABLE `course_implementation`
  ADD INDEX (`deleted_at`),
  ADD FOREIGN KEY (`folder_id`) REFERENCES `folder`(`id`)
    ON UPDATE NO ACTION
    ON DELETE NO ACTION;
