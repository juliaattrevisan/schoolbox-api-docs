ALTER TABLE `class_period` ADD COLUMN `teacher_id` INT(11) NULL AFTER `period_id`;
ALTER TABLE `class_period` ADD INDEX `class_period_teacher_id`  (`teacher_id`  ASC);
ALTER TABLE `class_period` ADD COLUMN `room` VARCHAR(64) NULL AFTER `teacher_id`;
