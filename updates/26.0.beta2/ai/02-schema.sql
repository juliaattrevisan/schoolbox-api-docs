-- Rename plural to singular
rename table dialog_agent_scenario_criteria to dialog_agent_scenario_criterion;

-- AI-generated feedback for the responses made by students
-- This is each cell's feedback value (ie. the feedback per scenario criterion)
CREATE TABLE `dialog_agent_feedback_criterion` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `feedback_id` INT NOT NULL,
    `scenario_criterion_id` INT NOT NULL,
    `body` TEXT,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_dafc_feedback_idx` (`feedback_id` ASC),
    CONSTRAINT `fk_dafc_feedback`
        FOREIGN KEY (`feedback_id`)
        REFERENCES `dialog_agent_feedback` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dafc_scenario_criterion_idx` (`scenario_criterion_id` ASC),
    CONSTRAINT `fk_dafc_scenario_criterion`
        FOREIGN KEY (`scenario_criterion_id`)
        REFERENCES `dialog_agent_scenario_criterion` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- Ditch the old column/keys arrangement
alter table dialog_agent_feedback
    drop foreign key fk_daf_criteria;

drop index fk_daf_criteria_idx on dialog_agent_feedback;

alter table dialog_agent_feedback
    drop column body;

alter table dialog_agent_feedback
    drop column criteria_id;

-- Restore some keys and stuff
alter table dialog_agent_feedback
    add constraint fk_daf_learner_id
        foreign key (learner_id) references user (id);

