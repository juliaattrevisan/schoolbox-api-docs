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
