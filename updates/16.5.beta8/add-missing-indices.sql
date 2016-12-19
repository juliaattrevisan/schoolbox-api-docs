ALTER TABLE `submission_return_file`
  ADD FOREIGN KEY (`submission_return_id`) REFERENCES `submission_return`(`id`);

ALTER TABLE `submission_return`
  ADD INDEX (`student_submission_id`);

ALTER TABLE `task_completion`
  CHANGE `student_id` `student_id` INT(11) UNSIGNED NOT NULL,
  CHANGE `marking_user_id` `marking_user_id` INT(11) UNSIGNED NULL,
  ADD FOREIGN KEY (`student_id`) REFERENCES `user`(`id`),
  ADD FOREIGN KEY (`marking_user_id`) REFERENCES `user`(`id`);
