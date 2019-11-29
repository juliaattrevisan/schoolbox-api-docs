-- Remove user_id column
ALTER TABLE `sis_class_user`
    DROP COLUMN `user_id`,
    DROP INDEX `class_user_user_id` ;

-- Remove teacher_id column
ALTER TABLE `sis_class_period`
    DROP COLUMN `teacher_id`,
    DROP INDEX `class_period_teacher_id` ;
