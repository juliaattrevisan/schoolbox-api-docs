-- To keep track of when the terminology was last modified so browser cache can be invalidated

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
    (
        'terminology_last_modified',
        UNIX_TIMESTAMP(),
        'Terminology Last Modified At',
        'The last time the terminology was modified',
        '',
        (SELECT id FROM lu_config_field WHERE `field` = 'input'),
        (SELECT id FROM lu_config_type WHERE `type` = 'Localisation'),
        'true',
        'true',
        'false',
        'settings',
        NULL
    );
