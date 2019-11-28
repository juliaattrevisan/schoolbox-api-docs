-- Restore indexes
ALTER TABLE `sis_class_period`
    ADD INDEX `class_period_teacher_external_id` (`teacher_external_id` ASC);

ALTER TABLE `sis_class_user`
    ADD INDEX `class_user_external_id` (`external_id` ASC);
