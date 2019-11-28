-- Remove user_id column
ALTER TABLE `sis_class_user`
    DROP COLUMN `user_id`,
    DROP INDEX `class_user_user_id` ;
