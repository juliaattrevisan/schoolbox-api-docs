-- Add Geosnapshot api key input field
-- 3rd Party Services
INSERT INTO `config`
    (`name`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('geosnapshot_api_key',
        'GeoSnapShot API Key',
        'The key used to securely integrate GeoSnapShots, this will be provided when the GeoSnapShot service is enabled',
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
        'GeoSnapShot Integration',
        'If ON, parents may view photos of their students uploaded to GeoSnapShots and photo galleries embedded into pages, note you must also have a valid API key.',
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
VALUES (230, 'GeoSnapShot is not available at this time; try again later.');
