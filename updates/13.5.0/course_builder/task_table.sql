-- Table for putting tasks into.
CREATE TABLE `task` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `category_id` INT NOT NULL ,
  `type` TINYINT NOT NULL ,
  `title` VARCHAR(250) NOT NULL ,
  `link` VARCHAR(250) NULL ,
  `assess_id` INT NULL ,
  `box_id` INT NULL ,
  `user_id` INT NOT NULL ,
  `created_at` DATETIME NOT NULL ,
  `deleted_at` DATETIME NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `task_category_id` (`category_id` ASC) ,
  INDEX `task_assess_id` (`assess_id` ASC) ,
  INDEX `task_box_id` (`box_id` ASC) ,
  INDEX `task_user_id` (`user_id` ASC) ,
  INDEX `task_deleted_at` (`deleted_at` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

-- Lookup table for task types
CREATE TABLE `lu_task_type` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `label` VARCHAR(250) NOT NULL ,
  `class` VARCHAR(250) NOT NULL ,
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;
INSERT INTO `lu_task_type`
  (`id`, `label`,                   `class`) VALUES
  (1,    'Link',                    'link'),
  (2,    'Student Self-Assessment', 'self_assess'),
  (3,    'Teacher Assessment',      'teacher_assess'),
  (4,    'Due Work Item',           'due_work'),
  (5,    'Online Assessment',       'online_assess')
;

-- Add reference to tasks table into assessments and submissions.
ALTER TABLE `assess` ADD COLUMN `task_id` INT NULL DEFAULT NULL AFTER `user_id` ;
ALTER TABLE `assess` ADD INDEX `assess_task_id` (`task_id` ASC) ;
ALTER TABLE `submission_box` ADD COLUMN `task_id` INT NULL DEFAULT NULL AFTER `owner` ;
ALTER TABLE `submission_box` ADD INDEX `submission_box_task_id` (`task_id` ASC) ;
