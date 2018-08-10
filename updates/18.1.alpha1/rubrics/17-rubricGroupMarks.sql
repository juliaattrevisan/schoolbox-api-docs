/**
 * Normalized schema for rubric group (commonly referred to as "capability") marks.
 * These will be extracted from `rubric_descriptor_mark` values.
 */
CREATE TABLE `rubric_group_mark` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `return_id` INT(11) UNSIGNED NOT NULL,
    `rubric_group_id` INT(11) UNSIGNED NOT NULL,
    `value` DECIMAL(14,11) UNSIGNED DEFAULT NULL,
    `valid` BOOL NOT NULL DEFAULT TRUE,
    PRIMARY KEY (`id`),
    KEY `rubric_group_id` (`rubric_group_id`),
    KEY `return_id` (`return_id`),
    CONSTRAINT `fk_rubric_group_mark_return_id`
    FOREIGN KEY (`return_id`)
    REFERENCES `submission_return` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION,
    CONSTRAINT `fk_rubric_group_mark_rubric_group_id`
    FOREIGN KEY (`rubric_group_id`)
    REFERENCES `rubric_group` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
