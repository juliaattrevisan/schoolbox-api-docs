ALTER TABLE `user_jwt_id`
    ADD COLUMN `updated_at` datetime        DEFAULT NULL after `created_at`,
    ADD COLUMN `updated_by` int(4) unsigned DEFAULT NULL after `created_by`;
