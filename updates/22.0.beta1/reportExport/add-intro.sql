INSERT INTO `lu_config_type` (`type`, `sequence`)
VALUES ('Report Export', (SELECT MAX(sequence) + 1 FROM lu_config_type lct));

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,
                      `required`, `module`, `sequence`)
VALUES ('report_export_introduction',
        '',
        'Introduction message',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'textarea'),
        NULL,
        (SELECT id FROM lu_config_type WHERE type = 'Report Export'),
        'true',
        'false',
        'false',
        'settings',
        1);
