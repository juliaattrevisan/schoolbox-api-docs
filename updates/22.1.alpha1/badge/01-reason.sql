-- Add a reason column so people can explain why they're awrding this badge

ALTER TABLE `badge_user`
ADD COLUMN `reason` TEXT NULL DEFAULT NULL AFTER `status`;
