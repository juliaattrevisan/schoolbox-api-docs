-- Add TurnItIn api key input field
-- 3rd Party Services
INSERT INTO `config`
(`name`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
    ('turnitin_api_key',
     'TurnItIn API Key',
     'The key used to securely integrate TurnItIn; this will be provided when the service is enabled',
     (SELECT id FROM lu_config_field WHERE field = 'input'),
     (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
     'true',
     'true',
     'false',
     'settings',
     (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (
         SELECT id FROM lu_config_type WHERE type = '3rd Party Services'
     ))
);