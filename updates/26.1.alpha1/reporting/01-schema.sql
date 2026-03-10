-- Reporting Activity in the swamp
CREATE TABLE `reporting` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `instructor_mark_type` INT,
    `mark_type` INT NOT NULL,
    `instructor_grade_group_id` INT,
    `grade_group_id` INT,
    `instructor_max_mark` DECIMAL(14, 11),
    `max_mark` DECIMAL(14, 11),
    `mark_visibility` TINYINT,
    `feedback_comments_enable` TINYINT COMMENT 'a toggle: if enabled, staff can write comments in their reporting feedbacks',
    `supporting_data_enable` TINYINT COMMENT 'Supporting Data section is shown',
    -- I cannot think of a succinct way to summarise these date ranges; sry for long enum values
    `supporting_data_range` ENUM(
        'current_academic_period',
        'previous_academic_period',
        'current_and_previous_academic_period',
        'current_academic_year',
        'current_and_previous_academic_year'
    ) COMMENT 'These translate into from/to date range for supporting data',
    `supporting_data_folders` ENUM('current_only', 'any_folder') COMMENT 'corresponds to "the students in my folder specifically", or "any folder where my students had a published feedback"',
    `supporting_data_markbook_enable` TINYINT COMMENT 'a toggle: if enabled, the minimal mega markbook is generated',
    `created_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `updated_by` INT UNSIGNED NOT NULL,
    `deleted_at` DATETIME,
    `deleted_by` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_r_assessment_idx` (`assessment_id` ASC),
    CONSTRAINT `fk_r_assessment`
        FOREIGN KEY (`assessment_id`)
        REFERENCES `assessment` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_r_createdby_idx` (`created_by` ASC),
    CONSTRAINT `fk_r_createdby`
        FOREIGN KEY (`created_by`)
        REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_r_updatedby_idx` (`updated_by` ASC),
    CONSTRAINT `fk_r_updatedby`
        FOREIGN KEY (`updated_by`)
        REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_r_deletedby_idx` (`deleted_by` ASC),
    CONSTRAINT `fk_r_deletedby`
        FOREIGN KEY (`deleted_by`)
        REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- This is different to curriculum_node_for_assessment despite being the same schema.
-- rcn means "filter the mega markbook to only show assessments that used descendents of this curriculum node(s)"
-- It does not mean "this reporting activity is associated with these curriculum nodes" which is what rcn means.
--
-- So, it's the same schema structure but has a different contextual usage.
CREATE TABLE `reporting_curriculum_node` (
    `reporting_id` INT NOT NULL,
    `curriculum_node_id` INT NOT NULL,
    UNIQUE INDEX `fk_rc_unique` (reporting_id, curriculum_node_id),
    INDEX `fk_rcn_reporting_id_idx` (`reporting_id` ASC),
    CONSTRAINT `fk_rcn_reporting_id`
        FOREIGN KEY (`reporting_id`)
            REFERENCES `reporting` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_rcn_cni_curriculum_node_idx` (`curriculum_node_id` ASC),
    CONSTRAINT `fk_rcn_cni_curriculum_node`
        FOREIGN KEY (`curriculum_node_id`)
            REFERENCES `curriculum_node` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- Similarly, this means "filter the mega markbook to only show assessments that had one or more of these work types"
-- It does not mean "this reporting activity has this work type" (that's on the assessment table)
CREATE TABLE `reporting_work_type` (
    `reporting_id` INT NOT NULL,
    `work_type_id` INT NOT NULL,
    UNIQUE INDEX `fk_aw_unique` (reporting_id, work_type_id),
    INDEX `fk_rwt_reporting_id_idx` (`reporting_id` ASC),
    CONSTRAINT `fk_rwt_reporting_id`
        FOREIGN KEY (`reporting_id`)
            REFERENCES `reporting` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_rwt_wti_work_type_idx` (`work_type_id` ASC),
    CONSTRAINT `fk_rwt_wti_work_type`
        FOREIGN KEY (`work_type_id`)
            REFERENCES `lu_work_type` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- A somewhat teacher-discretionary feedback that is meant to represent the student's achievements holistically
--
-- Note that the swamp REQUIRES you know the direct relationship between a row in this table and the subobject
-- to which it belongs (ie. each row knows its reporting.id without having to dig through the relationships)
-- Just Another Swamp Secret
CREATE TABLE `reporting_feedback` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `reporting_id` INT NOT NULL,
    `norm_mark` DECIMAL(14, 11),
    `mark` VARCHAR(255),
    `comment` MEDIUMTEXT,
    `learner_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_rf_reporting_idx` (`reporting_id` ASC),
    CONSTRAINT `fk_rf_reporting`
        FOREIGN KEY (`reporting_id`)
            REFERENCES `reporting` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_rf_learner_idx` (`learner_id` ASC),
    CONSTRAINT `fk_rf_learner`
        FOREIGN KEY (`learner_id`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);


-- Reporting is a Terminology row
INSERT INTO
    localisation (
    term,
    title,
    singular_form,
    plural_form,
    capitalise_first,
    indefinite_article
)
VALUES (
   'reporting',
   'Reporting',
   'reporting activity',
   'reporting activities',
   1,
   'a'
);
