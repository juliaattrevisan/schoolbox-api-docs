-- Rename this ambiguous field
UPDATE `config` SET `name`='boarder_leave_parent_notification_enabled' WHERE `name`='parent_notification';
