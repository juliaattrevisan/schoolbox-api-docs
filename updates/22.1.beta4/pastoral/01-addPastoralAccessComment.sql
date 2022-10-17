ALTER TABLE `pastoral_access`
    ADD COLUMN `comments_allowed` tinyint(1) DEFAULT 0 AFTER `access`;
ALTER TABLE `pastoral_audience`
    ADD COLUMN `comments_allowed` tinyint(1) DEFAULT 0 AFTER `access`;

-- update parent
-- NOTE: this will not add comment on for records which are not visible for students/parents
UPDATE `pastoral_access`
SET `comments_allowed` = 1
WHERE `role_type` = 'parent'
  AND `access` > 0
  AND `deleted_at` IS NULL
  AND `pastoral_id` IN
      (SELECT `id`
       FROM `pastoral`
       WHERE `parent_comments_allowed` = 1
         AND `deleted_at` IS NULL);

-- update student
UPDATE `pastoral_access`
SET `comments_allowed` = 1
WHERE `role_type` = 'student'
  AND `access` > 0
  AND `deleted_at` IS NULL
  AND `pastoral_id` IN
      (SELECT `id`
       FROM `pastoral`
       WHERE `student_comments_allowed` = 1
         AND `deleted_at` IS NULL);

-- staff role
UPDATE `pastoral_access`
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

-- staff role
UPDATE `pastoral_access`
    JOIN `user` ON `user_id` = `user`.`id` AND `user`.`deleted_at` IS NULL
    JOIN `groups` ON `user`.`group_id` = `groups`.`id`
SET `pastoral_access`.`comments_allowed` = 1
WHERE `pastoral_access`.`deleted_at` IS NULL
  AND `access` > 0
  AND `groups`.`group_type` = 'staff'
;

-- pastoral group
UPDATE `pastoral_access`
SET `comments_allowed` = 1
WHERE `group_id` IN
      (SELECT `id`
       FROM `pastoral_group`
       WHERE `deleted_at` IS NULL
      )
  AND `access` > 0
  AND `deleted_at` IS NULL
;

-- rename removed columns
ALTER TABLE `pastoral`
    CHANGE COLUMN `parent_comments_allowed` `_parent_comments_allowed` tinyint(1) DEFAULT 0;
ALTER TABLE `pastoral`
    CHANGE COLUMN `student_comments_allowed` `_student_comments_allowed` tinyint(1) DEFAULT 0;
