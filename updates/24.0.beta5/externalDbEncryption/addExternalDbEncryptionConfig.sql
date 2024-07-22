SELECT @selectField := id FROM lu_config_field WHERE field = 'select';
SELECT @inputType := id FROM lu_config_type WHERE type = 'External DB';

select @inputSequence := sequence FROM config WHERE name = 'external_cache_timeout';
UPDATE config SET sequence = sequence + 1 WHERE type_id = @inputType AND sequence >= @inputSequence;

INSERT INTO config (
    `name`,
    `value`,
    `title`,
    `description`,
    `options`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'external_db_encryption',
    'false',
    'Database Encryption Level',
    'Select the encryption level to connect to the SQL server.',
    'false:true:mandatory:optional:strict',
    (SELECT id FROM lu_config_field WHERE field = 'select'),
    @inputType,
    'true',
    'false',
    'false',
    'settings',
    @inputSequence
);

