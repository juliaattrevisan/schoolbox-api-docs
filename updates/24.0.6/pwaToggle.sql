-- Module option for report export
INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES ('pwa_enable',
        '1',
        'Enable PWA Notifications/Install',
        'If ON, PWA notifications and install buttons will appear',
        (SELECT id FROM lu_config_field WHERE field = 'boolean'),
        (SELECT id FROM lu_config_type WHERE type = 'Modules'),
        'true',
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
