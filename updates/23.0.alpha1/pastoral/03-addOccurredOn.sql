ALTER TABLE `pastoral` ADD COLUMN `occurred_on` datetime DEFAULT NULL AFTER `modified_by_user_id`;
ALTER TABLE `pastoral_revision` ADD COLUMN `occurred_on` datetime DEFAULT NULL AFTER `modified_by_user_id`;
