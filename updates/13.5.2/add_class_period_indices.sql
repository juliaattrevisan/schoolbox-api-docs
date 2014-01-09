ALTER TABLE `class_period`
  ADD INDEX `class_period_class_id`  (`class_id`  ASC),
  ADD INDEX `class_period_period_id` (`period_id` ASC);