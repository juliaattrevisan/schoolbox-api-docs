-- Add all user profile fields that ought to be there, but aren't
ALTER TABLE `user_profile` ADD COLUMN `dob` DATE   NULL AFTER `user_id`;
ALTER TABLE `user_profile` ADD COLUMN `level` INT(20)   NULL AFTER `dob`;
ALTER TABLE `user_profile` ADD COLUMN `homegroup` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `level`;
ALTER TABLE `user_profile` ADD COLUMN `house` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `homegroup`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1Name` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `house`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1HomePhone` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1Name`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1WorkPhone` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1HomePhone`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1MobilePhone` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1WorkPhone`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1Address` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1MobilePhone`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1Suburb` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1Address`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1Postcode` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1Suburb`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1Email` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1Postcode`;
ALTER TABLE `user_profile` ADD COLUMN `guardian1MailAddress` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1Email`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2Name` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian1MailAddress`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2HomePhone` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2Name`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2WorkPhone` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2HomePhone`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2MobilePhone` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2WorkPhone`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2Address` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2MobilePhone`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2Suburb` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2Address`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2Postcode` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2Suburb`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2Email` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2Postcode`;
ALTER TABLE `user_profile` ADD COLUMN `guardian2MailAddress` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `guardian2Email`;

-- I think these fields are deprecated, but I'll add them for consistency's sake
ALTER TABLE `user_profile` ADD COLUMN `title` VARCHAR(16)  COLLATE utf8_general_ci NULL AFTER `guardian2MailAddress`;
ALTER TABLE `user_profile` ADD COLUMN `given_name` VARCHAR(128)  COLLATE utf8_general_ci NULL AFTER `title`;
ALTER TABLE `user_profile` ADD COLUMN `preferred_name` VARCHAR(32)  COLLATE utf8_general_ci NULL AFTER `given_name`;
ALTER TABLE `user_profile` ADD COLUMN `last_name` VARCHAR(64)  COLLATE utf8_general_ci NULL AFTER `preferred_name`;
ALTER TABLE `user_profile` ADD COLUMN `birth_date` DATETIME  COLLATE utf8_general_ci NULL AFTER `last_name`;
ALTER TABLE `user_profile` ADD COLUMN `gender` INT(11)  COLLATE utf8_general_ci NULL AFTER `birth_date`;
ALTER TABLE `user_profile` ADD COLUMN `email` VARCHAR(64)  COLLATE utf8_general_ci NULL AFTER `gender`;
