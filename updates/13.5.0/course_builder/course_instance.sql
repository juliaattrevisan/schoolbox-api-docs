-- Table for putting instances of units into.
CREATE TABLE `course_unit_instance` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `component_id` INT NOT NULL,
  `unit_category_id` INT NOT NULL,
  `title` VARCHAR(250) NOT NULL,
  `open_date` DATETIME NOT NULL,
  `due_date` DATETIME NOT NULL,
-- Shouldn't need order, because open_date and due_date will be used for ordering
  `created_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `course_unit_instance_component_id` (`component_id` ASC),
  INDEX `course_unit_instance_unit_category_id` (`unit_category_id` ASC),
  INDEX `course_unit_instance_open_date` (`open_date` ASC),
  INDEX `course_unit_instance_due_date` (`due_date` ASC),
  INDEX `course_unit_instance_deleted_at` (`deleted_at` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

-- Table for unit tasks
-- Most of these fields will be copied from the base task
CREATE TABLE `task_instance` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_id` INT NOT NULL,
  `course_unit_instance_id` INT NOT NULL,
  `title` VARCHAR(250) NOT NULL,
  `link` VARCHAR(250) NULL,
  `requires_confirmation` BOOL NOT NULL DEFAULT FALSE,
  `assess_id` INT NULL,
  `box_id` INT NULL,
  `user_id` INT NOT NULL,
  `order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `task_instance_category_id` (`course_unit_instance_id` ASC),
  INDEX `task_instance_assess_id` (`assess_id` ASC),
  INDEX `task_instance_box_id` (`box_id` ASC),
  INDEX `task_instance_user_id` (`user_id` ASC),
  INDEX `task_instance_order` (`order` ASC),
  INDEX `task_instance_deleted_at` (`deleted_at` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;

-- Later, add student's completion stats.
CREATE TABLE `task_instance_completion` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_instance_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `completion_date` DATETIME NULL DEFAULT NULL,
  `completion_status` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `task_instance_completion_task_instance_id` (`task_instance_id` ASC),
  INDEX `task_instance_completion_student_id` (`student_id` ASC)
) DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci;
