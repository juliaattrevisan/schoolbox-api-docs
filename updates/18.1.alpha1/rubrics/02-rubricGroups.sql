/**
 * Normalized schema for rubric groups (commonly referred to as "capabilities").
 * These will be extracted from `rubric_group`.
 */
CREATE TABLE `rubric_group` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    -- Nullable in rubric_criterion, but should not be?
    `name` varchar(1000) CHARACTER SET utf8mb4 DEFAULT NULL,
    `parent_id` int(11) NOT NULL,
    `parent_type` enum('submission','category','curriculum') CHARACTER SET utf8mb4 DEFAULT NULL,
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `deleted` (`deleted_at`),
    KEY `parent_id` (`parent_id`,`parent_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
