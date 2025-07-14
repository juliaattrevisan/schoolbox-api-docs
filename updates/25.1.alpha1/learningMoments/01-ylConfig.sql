ALTER TABLE `lu_year_level`
    ADD COLUMN `learning_moments_moderation` tinyint(1) DEFAULT 0 AFTER `birthday_celebration_enabled`;
