-- update sequence for existing config
UPDATE `config` SET `sequence` = `sequence` + 1 WHERE `module` = 'report-export';

-- report introduction message
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,
                      `required`, `module`, `sequence`)
VALUES ('report_title',
        '',
        'Report title',
        'This will be displayed at the top of exported reports.',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        NULL,
        NULL,
        'true',
        'false',
        'true',
        'report-export',
        1);
