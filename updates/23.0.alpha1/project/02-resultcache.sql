-- Project aggregations saved here

CREATE TABLE `project_result` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `marker_id` INT DEFAULT NULL,
  `mark` VARCHAR(64) NULL,
  `norm_mark` DECIMAL(14,11) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `project_id` (`project_id` ASC),
  INDEX `user_id` (`user_id` ASC),
  INDEX `project_id_user_id` (`project_id` ASC, `user_id` ASC),
  INDEX `project_id_user_id_marker_id` (`project_id` ASC, `user_id` ASC, `marker_id` ASC))
;
