INSERT INTO `lu_config_type` (`type`, `sequence`)
VALUES ('Report Export', (SELECT MAX(sequence) + 1 FROM lu_config_type lct));
-- report introduction message
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,
                      `required`, `module`, `sequence`)
VALUES ('report_school_logo',
        '',
        'Report school logo',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'file'),
        NULL,
        (SELECT id FROM lu_config_type WHERE type = 'Report Export'),
        'true',
        'false',
        'false',
        'settings',
        1),
       ('report_export_introduction',
        '',
        'Introduction message',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'html'),
        NULL,
        (SELECT id FROM lu_config_type WHERE type = 'Report Export'),
        'true',
        'false',
        'false',
        'settings',
        2);

