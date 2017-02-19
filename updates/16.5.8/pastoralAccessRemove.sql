UPDATE `pastoral_access` SET `deleted_at` = '1970-01-01 00:00:00' WHERE `user_id` NOT IN (SELECT `id` FROM `user`);
UPDATE `pastoral_access` SET `deleted_at` = '1970-01-01 00:00:00' WHERE `user_id` IN (SELECT `id` FROM `user` WHERE `deleted_at` IS NOT NULL);
UPDATE `pastoral_access` SET `deleted_at` = '1970-01-01 00:00:00' WHERE `role_id` NOT IN (SELECT `id` FROM `groups`);
