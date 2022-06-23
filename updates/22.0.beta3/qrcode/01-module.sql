-- Module option for qr login
INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES ('qr_login_enabled',
        -- Enable if wellbeing,portalplus,pro,elite
        (SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value IN ('wellbeing','portalplus','pro','elite')) t1),
        'Enable QR Code Login',
        'If ON, QR codes can be turned on for year levels.',
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
