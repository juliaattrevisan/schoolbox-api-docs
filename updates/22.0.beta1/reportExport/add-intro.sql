ALTER TABLE config
    MODIFY COLUMN `module` ENUM ('settings','comms','custom','oauth','schoolData','lti','report-export') COLLATE utf8mb4_unicode_ci DEFAULT 'settings';
-- report introduction message
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,
                      `required`, `module`, `sequence`)
VALUES ('report_school_logo',
        '',
        'Report school logo',
        'If provided, this image will be displayed at the top of exported reports.',
        (SELECT id FROM lu_config_field WHERE field = 'file'),
        NULL,
        NULL,
        'true',
        'false',
        'false',
        'report-export',
        1),
       ('report_export_introduction',
        '',
        'Introduction message',
        'If provided, this text will be displayed at the beginning of exported reports.',
        (SELECT id FROM lu_config_field WHERE field = 'html'),
        NULL,
        NULL,
        'true',
        'false',
        'false',
        'report-export',
        2);

