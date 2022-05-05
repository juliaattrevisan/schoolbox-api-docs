
-- Module option
-- Clipboard is plan-specific
INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('clipboard_enabled',
        '0',
        'Enable Clipboard Integration',
        'If ON, Fixtures data will be imported from Clipboard; all Fixtures management should be performed in Clipboard.',
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
VALUES (231, 'Clipboard is not available at this time; try again later.');
