INSERT INTO `config`(
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`
)
VALUES (
    'cyberhound_api_url',
    '',
    'CyberHound Appliance API URL',
    'API URL for your CyberHound Proxy appliance, if you have one. (Include HTTP or HTTPS.)',
    1,
    NULL,
    10, -- 3rd party services
    'true',
    'false',
    'false'
), (
    'cyberhound_api_shared_key',
    '',
    'CyberHound Appliance API Shared Key',
    'API Shared Key for your CyberHound Proxy appliance, if you have one.',
    1,
    NULL,
    10, -- 3rd party services
    'true',
    'false',
    'false'
);
