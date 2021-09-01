-- Feature flag for Safe Exam Browser
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,
                      `required`, `module`, `sequence`)
VALUES ('lms_seb_enable',
        '0',
        'Enable Safe Exam Browser',
        'If toggled to "ON", teachers will be able to enable the Safe Exam Browser to ensure students are prevented from cheating during assessments.',
        (SELECT id FROM lu_config_field WHERE field = 'boolean'),
        '',
        (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT MAX(sequence) + 1
         FROM config c
         WHERE type_id = (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy')));
