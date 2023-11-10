ALTER TABLE `assess`
ADD COLUMN `learnosity_reference` VARCHAR(255) NULL AFTER `external_id`;

ALTER TABLE `assess_attempt`
    ADD COLUMN `learnosity_reference` VARCHAR(255) NULL AFTER `max_mark`;

-- Remember a percentage value so you don't have to
-- calculate it manually somewhere else
ALTER TABLE `assess_attempt`
    ADD COLUMN `norm_mark` DECIMAL(14,11) NULL AFTER `max_mark`;

-- A comment about the overall attempt made by the student
ALTER TABLE `assess_attempt`
    ADD COLUMN `comment` TEXT NULL AFTER `norm_mark`;

-- Store per-question commentary locally
CREATE TABLE `quiz_feedback` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `attempt_id` INT NOT NULL,
    `comment` TEXT NULL,
    `learnosity_reference` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
PRIMARY KEY (`id`));

-- Add a uuid to course spec/impl, unit spec/impl
ALTER TABLE `course_specification`
    CHANGE COLUMN `subject_code` `subject_code` VARCHAR(20) NOT NULL DEFAULT '' AFTER `folder_id`,
    ADD COLUMN `uuid` VARCHAR(36) NULL AFTER `subject_code`,
    ADD UNIQUE INDEX `uuid_UNIQUE` (`uuid` ASC);

ALTER TABLE `course_unit_specification`
    ADD COLUMN `uuid` VARCHAR(36) NULL AFTER `course_specification_id`,
    ADD UNIQUE INDEX `uuid_UNIQUE` (`uuid` ASC);

-- Add Reading Time
ALTER TABLE `assess`
    ADD COLUMN `reading_time_duration` INT NOT NULL DEFAULT 0 AFTER `duration`;

-- Add Shuffle Items
ALTER TABLE `assess`
    ADD COLUMN `shuffle_items` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reading_time_duration`;

-- Add Exam Mode
ALTER TABLE `assess`
    ADD COLUMN `prompts_mode` TINYINT(1) NOT NULL DEFAULT 0 AFTER `shuffle_items`;

-- Add Mark Type and Grade Group
-- Defaults to Total Mark Fractional
ALTER TABLE `assess`
    ADD COLUMN `mark_type_id` INT(11) NOT NULL DEFAULT 3 AFTER `weight`,
    ADD COLUMN `grade_group_id` INT(11) NULL AFTER `mark_type_id`;

-- assess_attempt.mark is now the human readable mark, I guess?
-- We never really had to store the values that *inform* the norm- and human-readable
-- marks before Learnosity...this is kind of synonymous with submission_return's usage
ALTER TABLE `assess_attempt`
    CHANGE COLUMN `mark` `mark` VARCHAR(255) NULL DEFAULT NULL ;

-- Reset duration to 0 for Unlimited, rather than -1
ALTER TABLE `assess`
    ADD COLUMN `_duration` INT NOT NULL DEFAULT 0 AFTER `duration`;

UPDATE `assess`
    SET _duration = duration;

UPDATE `assess`
SET duration = 0
WHERE duration = -1;