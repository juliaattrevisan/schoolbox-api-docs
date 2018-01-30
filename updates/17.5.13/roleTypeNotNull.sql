UPDATE `groups` SET `name` = 'Unknown' WHERE `name` IS NULL;
UPDATE `groups` SET `group_type` = 'guest' WHERE `group_type` IS NULL;
UPDATE `groups` SET `can_change_password` = 'false' WHERE `can_change_password` IS NULL;


ALTER TABLE `groups`
  CHANGE COLUMN `name` `name` VARCHAR(50) CHARACTER SET 'utf8mb4' NOT NULL ,
  CHANGE COLUMN `group_type` `group_type` ENUM('guest', 'student', 'parent', 'staff') CHARACTER SET 'utf8mb4' NOT NULL ,
  CHANGE COLUMN `can_change_password` `can_change_password` ENUM('true', 'false') CHARACTER SET 'utf8mb4' NOT NULL ;
