UPDATE `pastoral_access`
SET `deleted_at` = '1970-02-03 00:00:00'
WHERE `role_id` IS NOT NULL AND `pastoral_id` IN (
    SELECT `id` FROM `pastoral` WHERE `_private` = 1
);