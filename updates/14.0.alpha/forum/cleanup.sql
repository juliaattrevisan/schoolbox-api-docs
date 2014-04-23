ALTER TABLE `forumThread` CHANGE `views` `views` INT(11) DEFAULT 0 NOT NULL;
ALTER TABLE `forumThread` DROP COLUMN `last_post_user`;
ALTER TABLE `forumThread` DROP COLUMN `tempID`;