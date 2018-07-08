-- Store rubric group's uses in a due work
CREATE TABLE `rubric_group_usage` (
    `rubric_group_id` INT(11) NOT NULL,
    `submission_box_id` INT(11) UNSIGNED NOT NULL,
    `max_value` DECIMAL(14,11) UNSIGNED DEFAULT NULL,
    `sequence` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`rubric_group_id`,`submission_box_id`),
    KEY `rubric_group_id` (`rubric_group_id`),
    KEY `submission_box_id` (`submission_box_id`),
    CONSTRAINT `fk_rubric_group_usage_box_id`
        FOREIGN KEY (`submission_box_id`)
        REFERENCES `submission_box` (`id`)
        ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_rubric_group_usage_rubric_group_id`
        FOREIGN KEY (`rubric_group_id`)
        REFERENCES `rubric_group` (`id`)
        ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
