ALTER TABLE `badge_user`
    ADD COLUMN `reasoned_by` int(10) unsigned DEFAULT NULL AFTER `reason`,
    ADD COLUMN `reasoned_at` datetime         DEFAULT NULL AFTER `reason_by`;
