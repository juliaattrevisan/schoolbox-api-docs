-- Add a custom css field to profile buttons
-- this allows you to use them in conjunction with skin config
ALTER TABLE `profile_button`
    ADD COLUMN `css` VARCHAR(200) NULL DEFAULT NULL AFTER `url`;
