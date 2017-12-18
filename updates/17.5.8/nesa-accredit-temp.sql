-- Remember when someone was accredited so we can exclude them from future NESA reports
-- to help increase performance and acquisition/aggregation times
ALTER TABLE `user_professionaldevelopment` ADD COLUMN `accredited_at` DATETIME NULL AFTER `registration_state`;