/* Add control to skip students in the People Selector who haven't submitted work
 */
ALTER TABLE `submission_box` ADD COLUMN `skip_unsubmitted` INT(1) DEFAULT 0 NOT NULL COMMENT 'Skip students who haven\'t submitted Due Work, in the People Selector' AFTER `mark_visibility`;