-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
    SET c.sequence = c.sequence + 1
WHERE lct.type = 'Modules'
    AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='kami_enabled' LIMIT 1);

-- Enable Kami Worksheets
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'kami_worksheets_enabled',
    '1',
    'Enable Kami Worksheets',
    "If toggled to 'ON', the Kami Worksheets will be available in Due Work. If toggled to 'OFF', it will be unavailable for all users.",
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    null,
    (SELECT id FROM lu_config_type WHERE type = 'Modules'),
    'true',
    'true',
    'false',
    'settings',
    (SELECT sequence+1 FROM `config` c WHERE c.name = 'kami_enabled')
);
