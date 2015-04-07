INSERT INTO `config`(
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`
)
VALUES (
    'curriculum_tagging_enabled',
    '0',
    'Enable Curriculum Tagging',
    'Is the tagging of folders, due work items and quizzes with curriculum codes enabled? (This setting should not be enabled before curricula have been set up.)',
    3, -- boolean
    NULL,
    15, -- LMS Policy
    'true',
    'false',
    'false'
);
