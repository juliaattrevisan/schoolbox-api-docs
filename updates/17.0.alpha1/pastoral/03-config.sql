-- Create settings tab for pastoral care

INSERT INTO `lu_config_type` (`type`) VALUES ('Pastoral Care');

SET @configId = LAST_INSERT_ID();

UPDATE `config` SET `type_id` = @configId WHERE `name` like '%pastoral%';
UPDATE `config` SET `value` = 8, hidden = 1 WHERE `name` = 'pastoral_page_visible';