-- Each rubric grid has its settings stored here, against a particular rubric_instance.id, instead of having
-- its settings inside submission_box. This should let you have multiple grids per activity and not be married
-- to due work, specifically

CREATE TABLE rubric_instance
(
    `id`                               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id`                    INT UNSIGNED NOT NULL,
    `title`                            VARCHAR(255),
    `weight`                           DECIMAL(12,2),
    `rubric_capability_mark_type_id`   INT,
    `rubric_capability_grade_group_id` INT,
    `rubric_mark_range`                INT UNSIGNED,
    `created_at`                       DATETIME     NOT NULL,
    `updated_at`                       DATETIME     NOT NULL,
    `deleted_at`                       DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_ri_assessment_idx` (`assessment_id` ASC),
    CONSTRAINT `fk_ri_assessment`
        FOREIGN KEY (`assessment_id`)
            REFERENCES `assessment` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_ri_rcmti_idx` (`rubric_capability_mark_type_id` ASC),
    CONSTRAINT `fk_ri_rcmti`
        FOREIGN KEY (`rubric_capability_mark_type_id`)
            REFERENCES `submission_mark_type` (`mark_type_id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_ri_rcggi_idx` (`rubric_capability_grade_group_id` ASC),
    CONSTRAINT `fk_ri_rcggi`
        FOREIGN KEY (`rubric_capability_grade_group_id`)
            REFERENCES `submission_grade_group` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
)
    DEFAULT CHARACTER SET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

