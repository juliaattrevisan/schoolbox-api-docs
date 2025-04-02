-- Repair ill-defined collation/charset combos
-- Turns out storm's default collation is utf8mb4_general_ci which is wrong

ALTER TABLE `innodb_ft_stopword` COLLATE=utf8mb4_unicode_ci,
    CHANGE COLUMN `value` `value` varchar(18) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NOT NULL;

ALTER TABLE `lesson_plan_field` COLLATE=utf8mb4_unicode_ci,
    CHANGE COLUMN `description` `description` text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;

ALTER TABLE `lesson_plan_template_field` COLLATE=utf8mb4_unicode_ci,
    CHANGE COLUMN `description` `description` text CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL;
