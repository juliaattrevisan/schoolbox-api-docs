CREATE TABLE `assessment_participant` (
    `assessment_id` INT(10) UNSIGNED NOT NULL, -- TODO standardize on INT(11) SIGNED
    `student_id` INT(4) UNSIGNED NOT NULL,
    `open_date` DATETIME NULL,
    `due_date` DATETIME NULL,
    UNIQUE INDEX `assessment_participant_key` (`student_id` ASC, `assessment_id` ASC),
    INDEX `fk_assessment_participant_assessment_idx` (`assessment_id` ASC),
    CONSTRAINT `fk_assessment_participant_assessment`
    FOREIGN KEY (`assessment_id`)
    REFERENCES `assessment` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION,
    CONSTRAINT `fk_assessment_participant_participant`
    FOREIGN KEY (`student_id`)
    REFERENCES `user` (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION);
