-- Add new config into LMS Policy
SET @configType = (SELECT id FROM lu_config_type WHERE type = 'LMS Policy');
INSERT INTO config (
    `name`,
    `title`,
    `value`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'activity_feed_feedback_enabled',
    'Enable feedback on student activity feed',
    1,
    'If toggled to ''ON'', feedback on activities will be able to be shown on the student activity feed.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    @configType,
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence) + 1 FROM `config` c WHERE c.type_id = @configType)
);
