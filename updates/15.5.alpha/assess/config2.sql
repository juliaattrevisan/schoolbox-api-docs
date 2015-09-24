-- Rich text too
UPDATE `assess_luQuestionType` SET  `has_config` = 'false' WHERE `filename` = 'richtext';

-- Numeric has no config for assess, but does for survey.
-- Create separate answer type for survey.
UPDATE `assess_luQuestionType` SET
    `availability` = 'assess',
    `has_config` = 'false'
WHERE filename = 'numeric';
INSERT INTO `assess_luQuestionType` (
    `classname`,
    `filename`,
    `description`,
    `moodle`,
    `automated_marking`,
    `availability`,
    `has_legend`,
    `has_config`
)
    SELECT
        `classname`,
        `filename`,
        `description`,
        `moodle`,
        `automated_marking`,
        'survey',
        `has_legend`,
        'true'
    FROM `assess_luQuestionType`
    WHERE filename = 'numeric';
-- Update survey references to this answer type too
UPDATE `assess_question` `aq`
    INNER JOIN `assess_section` `as` ON `aq`.`section_id` = `as`.`section_id`
    INNER JOIN `assess` `a` ON `as`.`assess_id` = `a`.`assess_id`
    INNER JOIN `assess_luQuestionType` `aqt` ON `aq`.`type_id` = `aqt`.`type_id`
SET `aq`.`type_id` = LAST_INSERT_ID()
WHERE `aqt`.`filename` = 'numeric' AND `a`.`availability` = 'survey';

-- Do the same for short answer.
UPDATE `assess_luQuestionType` SET
    `availability` = 'assess',
    `has_config` = 'false'
WHERE filename = 'short';
INSERT INTO `assess_luQuestionType` (
    `classname`,
    `filename`,
    `description`,
    `moodle`,
    `automated_marking`,
    `availability`,
    `has_legend`,
    `has_config`
)
    SELECT
        `classname`,
        `filename`,
        `description`,
        `moodle`,
        `automated_marking`,
        'survey',
        `has_legend`,
        'true'
    FROM `assess_luQuestionType`
    WHERE filename = 'short';
-- Update survey references to this answer type too
UPDATE `assess_question` `aq`
    INNER JOIN `assess_section` `as` ON `aq`.`section_id` = `as`.`section_id`
    INNER JOIN `assess` `a` ON `as`.`assess_id` = `a`.`assess_id`
    INNER JOIN `assess_luQuestionType` `aqt` ON `aq`.`type_id` = `aqt`.`type_id`
SET `aq`.`type_id` = LAST_INSERT_ID()
WHERE `aqt`.`filename` = 'numeric' AND `a`.`availability` = 'short';