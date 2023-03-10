ALTER TABLE `pastoral_report`
    ADD COLUMN `action_status` ENUM ('pending', 'completed') CHARACTER SET 'ascii' DEFAULT NULL AFTER `actions`;
UPDATE `pastoral_report`
SET `action_status` = 'pending'
WHERE `include_uncompleted_actions_only` = 1;
-- rename removed columns
ALTER TABLE `pastoral_report`
    CHANGE COLUMN `include_uncompleted_actions_only` `_include_uncompleted_actions_only` tinyint(1) DEFAULT 0;
