-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 2
WHERE lct.type = 'Modules'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='messaging_enabled' LIMIT 1);

-- Enable PWA and PUSH Notifications
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
            'pwa_enable',
            '1',
            'Enable PWA Install',
            "If ON, PWA install buttons will appear",
            (SELECT id FROM lu_config_field WHERE field = 'boolean'),
            null,
            (SELECT id FROM lu_config_type WHERE type = 'Modules'),
            'true',
            'false',
            'false',
            'settings',
            (SELECT sequence+1 FROM `config` c WHERE c.name = 'messaging_enabled')
       ), (
            'web_push_notifications_enabled',
            '1',
            'Enable Web Push Notifications',
            "If ON, Web Push Notifications will be enabled and allow notifiction button will appear",
            (SELECT id FROM lu_config_field WHERE field = 'boolean'),
            null,
            (SELECT id FROM lu_config_type WHERE type = 'Modules'),
            'true',
            'false',
            'false',
            'settings',
            (SELECT sequence+2 FROM `config` c WHERE c.name = 'messaging_enabled')
);
