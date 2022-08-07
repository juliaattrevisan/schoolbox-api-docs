-- Feature flag for LTI Assessments
ALTER TABLE config
    MODIFY COLUMN `module` ENUM ('settings','comms','custom','oauth','schoolData','lti') COLLATE utf8mb4_unicode_ci DEFAULT 'settings';
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,
                      `required`, `module`, `sequence`)
VALUES ('lti_assessments_enable',
        '0',
        'Enable 3rd Party Assessments',
        'If toggled "ON", teachers may create assessments that contain an LTI 1.3 compatible learning tool.',
        (SELECT id FROM lu_config_field WHERE field = 'boolean'),
        '',
        NULL,
        -- Editable if basic,pro,elite
        (IF((SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value IN ('basic','pro','elite')) t1), 'true', 'false')),
        'false',
        'false',
        'lti',
        (SELECT MAX(sequence) + 1
         FROM config c
         WHERE module = 'lti'));
