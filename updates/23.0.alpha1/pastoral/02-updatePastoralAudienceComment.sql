-- set default comments_allowed for staff role
UPDATE `pastoral_audience`
SET `comments_allowed` = 1
WHERE `role_id` IN
      (SELECT `id`
       FROM `groups`
       WHERE `group_type` = 'staff'
         AND `deleted_at` IS NULL
      )
  AND `access` > 0
  AND `deleted_at` IS NULL
;

-- set default comments_allowed for users in a staff role
UPDATE `pastoral_audience`
    JOIN `user` ON `user_id` = `user`.`id` AND `user`.`deleted_at` IS NULL
    JOIN `groups` ON `user`.`group_id` = `groups`.`id`
SET `pastoral_audience`.`comments_allowed` = 1
WHERE `pastoral_audience`.`deleted_at` IS NULL
  AND `access` > 0
  AND `groups`.`group_type` = 'staff'
;

-- set default comments_allowed for pastoral group
UPDATE `pastoral_audience`
SET `comments_allowed` = 1
WHERE `group_id` IN
      (SELECT `id`
       FROM `pastoral_group`
       WHERE `deleted_at` IS NULL
      )
  AND `access` > 0
  AND `deleted_at` IS NULL
;
