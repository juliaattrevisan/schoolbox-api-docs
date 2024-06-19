SELECT @inputField := id FROM lu_config_field WHERE field = 'input';
SELECT @inputType := id FROM lu_config_type WHERE type = '3rd Party Services';

# Add kami_api_host before kami_api_key
SELECT @inputSequence := sequence FROM config WHERE name='kami_api_key';
UPDATE config SET sequence = sequence + 1 WHERE type_id = @inputType AND sequence >= @inputSequence;

# And insert
INSERT INTO config (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    sequence
) VALUES (
    'kami_api_host',
    'https://api.notablepdf.com/',
    'Kami API Host',
    'Kami API host for inline grading. Used to download and annotate submissions.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    @inputType,
    'false',
    'true',
    'false',
    'settings',
    @inputSequence
);
