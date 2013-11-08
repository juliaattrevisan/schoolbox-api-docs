-- Add a digest_schedule column
ALTER TABLE `groups`
  ADD COLUMN `digest_schedule` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '0: Never, 1: Weekly, 2: Daily'
  AFTER `can_change_password`
;
