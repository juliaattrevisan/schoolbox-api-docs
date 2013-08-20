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
);
