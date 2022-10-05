UPDATE `pastoral` SET `modified_by_user_id` = `owner_id` WHERE `modified_by_user_id` IS NULL;
ALTER TABLE `pastoral` MODIFY COLUMN `modified_by_user_id`int(11) unsigned NOT NULL;
UPDATE `pastoral_revision` SET `modified_by_user_id` = `owner_id` WHERE `modified_by_user_id` IS NULL;
ALTER TABLE `pastoral_revision` MODIFY COLUMN `modified_by_user_id`int(11) unsigned NOT NULL;
