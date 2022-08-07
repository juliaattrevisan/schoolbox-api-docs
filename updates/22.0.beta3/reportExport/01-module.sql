-- Module option for report export
INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES ('summative_report_enabled',
        '0',
        'Enable Summative Report',
        'If ON, the summative report export feature appears in the admin navigation.',
        (SELECT id FROM lu_config_field WHERE field = 'boolean'),
        (SELECT id FROM lu_config_type WHERE type = 'Modules'),
        -- Editable if plan in pro,elite
        (IF((SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value IN ('pro','elite')) t1), 'true', 'false')),
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
