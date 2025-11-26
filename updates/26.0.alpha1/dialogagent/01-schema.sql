-- A scenario contains multiple fields
CREATE TABLE `dialog_agent_scenario` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255),
    `description` TEXT,
    `prompt_template` TEXT,
    `type` ENUM('system', 'custom') NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`)
);

-- Fields in a particular scenario
CREATE TABLE `dialog_agent_scenario_field` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `scenario_id` INT NOT NULL,
    `title` VARCHAR(255),
    `slug` VARCHAR(255),
    `type` ENUM('textarea', 'switch', 'dropdown') NOT NULL,
    `meta` VARCHAR(255),
    `options` JSON,
    `sequence` INT,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_das_field_scenario_idx` (`scenario_id` ASC),
    CONSTRAINT `fk_das_field_scenario`
        FOREIGN KEY (`scenario_id`)
            REFERENCES `dialog_agent_scenario` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- A dialog agent uses one scenario exactly
CREATE TABLE `dialog_agent` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `scenario_id` INT NOT NULL,
    `language` VARCHAR(255) NULL,
    `helpfulness` ENUM('minimal', 'moderate', 'extreme') NULL,
    `use_diff_profile` INT NULL,
    `opening_message` TEXT,
    `rules` TEXT,
    `prompt` TEXT,
    `duration` INT,
    `created_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `updated_by` INT UNSIGNED NOT NULL,
    `deleted_at` DATETIME,
    `deleted_by` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_da_scenario_idx` (`scenario_id` ASC),
    CONSTRAINT `fk_da_scenario`
        FOREIGN KEY (`scenario_id`)
            REFERENCES `dialog_agent_scenario` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_da_assessment_idx` (`assessment_id` ASC),
    CONSTRAINT `fk_da_assessment`
        FOREIGN KEY (`assessment_id`)
            REFERENCES `assessment` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_da_createdby_idx` (`created_by` ASC),
    CONSTRAINT `fk_da_createdby`
        FOREIGN KEY (`created_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_da_updatedby_idx` (`updated_by` ASC),
    CONSTRAINT `fk_da_updatedby`
        FOREIGN KEY (`updated_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_da_deletedby_idx` (`deleted_by` ASC),
    CONSTRAINT `fk_da_deletedby`
        FOREIGN KEY (`deleted_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- Something the teacher writes for a particular field
CREATE TABLE `dialog_agent_scenario_field_context` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `scenario_field_id` INT NOT NULL,
    `dialog_agent_id` INT NOT NULL,
    `body` TEXT,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_dasfc_dasf_idx` (`scenario_field_id` ASC),
    CONSTRAINT `fk_dasfc_dasf`
        FOREIGN KEY (`scenario_field_id`)
            REFERENCES `dialog_agent_scenario_field` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dasfc_dialog_agent_idx` (`dialog_agent_id` ASC),
    CONSTRAINT `fk_dasfc_dialog_agent`
        FOREIGN KEY (`dialog_agent_id`)
            REFERENCES `dialog_agent` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- Some other ai process creates the feedback from these pieces of info
CREATE TABLE `dialog_agent_scenario_criteria` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `scenario_id` INT NOT NULL,
    `title` VARCHAR(255),
    `prompt` TEXT,
    `sequence` INT,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_dasc_dialog_agent_scenario_idx` (`scenario_id` ASC),
    CONSTRAINT `fk_dasc_dialog_agent_scenario`
        FOREIGN KEY (`scenario_id`)
            REFERENCES `dialog_agent_scenario` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- because for some reason this never had one
create index openai_log_session
    on openai_log (session_id);

-- This is what the students said
-- note that session_id is an ai session identifier, not your usual login session identifier
CREATE TABLE `dialog_agent_response` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `dialog_agent_id` INT NOT NULL,
    `learner_id` INT UNSIGNED NOT NULL,
    `authoritative` BOOLEAN,
    `session_id` CHAR(36),
    `started_at` DATETIME,
    `assistance_requested_at` DATETIME,
    `submitted_at` DATETIME,
    `submitted_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `updated_by` INT UNSIGNED NOT NULL,
    `deleted_at` DATETIME,
    `deleted_by` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_dar_dialog_agent_idx` (`dialog_agent_id` ASC),
    CONSTRAINT `fk_dar_dialog_agent`
        FOREIGN KEY (`dialog_agent_id`)
            REFERENCES `dialog_agent` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dar_learner_idx` (`learner_id` ASC),
    CONSTRAINT `fk_dar_learner`
        FOREIGN KEY (`learner_id`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dar_session_idx` (`session_id` ASC),
    CONSTRAINT `fk_dar_session`
        FOREIGN KEY (`session_id`)
            REFERENCES `openai_log` (`session_id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dar_submitted_by_idx` (`submitted_by` ASC),
    CONSTRAINT `fk_dar_submitted_by`
        FOREIGN KEY (`submitted_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dar_createdby_idx` (`created_by` ASC),
    CONSTRAINT `fk_dar_createdby`
        FOREIGN KEY (`created_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dar_updatedby_idx` (`updated_by` ASC),
    CONSTRAINT `fk_dar_updatedby`
        FOREIGN KEY (`updated_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_dar_deletedby_idx` (`deleted_by` ASC),
    CONSTRAINT `fk_dar_deletedby`
        FOREIGN KEY (`deleted_by`)
            REFERENCES `user` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);

-- AI-generated feedback for the responses made by students
-- Note that the swamp REQUIRES you know the direct relationship betweena row in this table and the subobject
-- to which it belongs (ie. each row knows its dialog_agent_id without having to dig through the relationships)
-- Just Another Swamp Secret
CREATE TABLE `dialog_agent_feedback` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `dialog_agent_id` INT NOT NULL,
    `response_id` INT NOT NULL,
    `criteria_id` INT NOT NULL,
    `body` TEXT,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_daf_dialog_agent_idx` (`dialog_agent_id` ASC),
    CONSTRAINT `fk_daf_dialog_agent`
        FOREIGN KEY (`dialog_agent_id`)
            REFERENCES `dialog_agent` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_daf_response_idx` (`response_id` ASC),
    CONSTRAINT `fk_daf_response`
        FOREIGN KEY (`response_id`)
            REFERENCES `dialog_agent_response` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION,
    INDEX `fk_daf_criteria_idx` (`criteria_id` ASC),
    CONSTRAINT `fk_daf_criteria`
        FOREIGN KEY (`criteria_id`)
            REFERENCES `dialog_agent_scenario_criteria` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION
);
