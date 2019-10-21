-- Rename this ambiguous field
UPDATE `config` SET `name`='boarder_leave_parent_notification_enabled' WHERE `name`='parent_notification';

-- Negatize the meaning
UPDATE `config` SET `value` = IF (`value`, 0, 1) WHERE `name` = 'boarder_leave_parent_notification_enabled';
