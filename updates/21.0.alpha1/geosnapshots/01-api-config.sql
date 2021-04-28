-- Add Geosnapshot api key input field
-- 3rd Party Services
INSERT INTO `config`
    (`name`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('geosnapshot_api_key',
        'Geosnapshot API Key',
        'Allow integration to Geosnapshot by providing an API Key.',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (
            SELECT id FROM lu_config_type WHERE type = '3rd Party Services'
        ))
    );

-- Module option
-- Geosnapshot is plan-specific
INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('geosnapshot_enabled',
        '0',
        'Geosnapshot Integration',
        'If ON, Geosnapshot integration is enabled.',
        (SELECT id FROM lu_config_field WHERE field = 'boolean'),
        (SELECT id FROM lu_config_type WHERE type = 'Modules'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (
            SELECT id FROM lu_config_type WHERE type = 'Modules'
        ))
    );

-- Generic error message for now
INSERT INTO `error` (id, message)
VALUES (230, 'Geosnapshot is not available at this time; try again later.');