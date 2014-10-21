-- Create the submission grade group table.
CREATE TABLE `submission_grade_group` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=INNODB CHARSET=utf8mb4;

-- and a default value
INSERT INTO `submission_grade_group` (`name`) VALUES ('Standard');
SELECT @standardId := `id` FROM `submission_grade_group` WHERE `name` = 'Standard' LIMIT 1;

-- and add this to submission grades.
ALTER TABLE `submission_grade`
  ADD COLUMN `submission_grade_group_id` INT NOT NULL AFTER `grade_id`,
  ADD INDEX (`submission_grade_group_id`);
UPDATE `submission_grade` SET `submission_grade_group_id` = @standardId;
