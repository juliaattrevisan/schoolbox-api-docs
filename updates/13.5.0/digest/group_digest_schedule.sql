-- Add a digest_schedule column
ALTER TABLE `groups`
  ADD COLUMN `digest_schedule` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '0: Never, 1: Weekly, 2: Daily'
  AFTER `can_change_password`
;

-- Populate based on the digest email config settings
-- Daily setting clobbers weekly, because daily digest would include all weekly
UPDATE `groups`
  SET `digest_schedule` = IF(
    (SELECT `value` FROM `config` WHERE `name` = 'digest_send_weekly'),
    1, 0
  )
;
UPDATE `groups`
  SET `digest_schedule` = IF(
    (SELECT `value` FROM `config` WHERE `name` = 'digest_send_daily'),
    2, 0
  )
;
