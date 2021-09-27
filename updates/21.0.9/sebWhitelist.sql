-- Feature flag for Safe Exam Browser
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,
                      `required`, `module`, `sequence`)
VALUES ('seb_whitelist',
        '',
        'Safe Exam Browser URL Whitelist',
        'Provide a list of URLs that students will be allowed to load when using the Safe Exam Browser. Filter expressions are permitted, for more information see SEB documentation.',
        (SELECT id FROM lu_config_field WHERE field = 'textarea'),
        '',
        (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT sequence + 1
         FROM config c
         WHERE `name` = 'lms_seb_enable'));

UPDATE config SET sequence = sequence + 1
WHERE sequence > (SELECT sequence FROM config WHERE `name` = 'seb_whitelist')
AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy');
