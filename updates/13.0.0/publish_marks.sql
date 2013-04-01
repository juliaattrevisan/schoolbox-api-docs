-- Add selectKey config type - I don't like select type and it not allowing
-- separate key type.
ALTER TABLE `config`
  CHANGE COLUMN `field` `field`
    ENUM('input','textarea','boolean','select','checkbox','bytes','selectKey') NULL
    DEFAULT 'input'
;

-- Add config setting for publishable marks
INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'submission_access',
  '2',
  'Default Submission Mark Visibility',
  'Who should marks for submissions be visible for by default? (This can be overridden on a per-submission level.)',
  'selectKey',
  '0;Private:1;Students:2;Students and Parents',
  'Policy',
  'true',
  'false',
  'false'
);

-- Add publish setting for submission boxes.
ALTER TABLE `submission_box` ADD `mark_visibility` INT DEFAULT 2;
