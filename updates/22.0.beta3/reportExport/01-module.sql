-- Module option for report export
INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES ('summative_report_enabled',
        '0',
        'Enable Summative Report',
        'If ON, the summative report export feature appears in the admin navigation.',
        (SELECT id FROM lu_config_field WHERE field = 'boolean'),
        (SELECT id FROM lu_config_type WHERE type = 'Modules'),
        'false',
        'false',
        'false',
        'settings',
        (SELECT MAX(sequence) + 1
         FROM config c
         WHERE type_id = (
             SELECT id
             FROM lu_config_type
             WHERE type = 'Modules'
         )));
