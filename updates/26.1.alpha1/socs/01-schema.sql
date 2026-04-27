-- Push down all the other configs in this area
-- Create a hole in config
UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'Modules'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='clipboard_enabled' LIMIT 1);

-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'socs_enabled',
           'Enable SOCS Integration',
           'If ON, Fixtures data will be imported from SOCS; all Fixtures management should be performed in SOCS.',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           (SELECT id FROM lu_config_type WHERE type = 'Modules'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT sequence+1 FROM `config` c WHERE c.name = 'clipboard_enabled')
       );


-- Push down all the other configs in this area
-- Create a hole in config
UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 3
WHERE lct.type = '3rd Party Services'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='clipboard_endpoint_token' LIMIT 1);

-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'socs_endpoint_host',
           'SOCS Endpoint Host',
           'The SOCS data endpoint is located here.',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT sequence+1 FROM `config` c WHERE c.name = 'clipboard_endpoint_token')
       );

-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'socs_endpoint_token',
           'SOCS Endpoint Token',
           'The authentication token granted by the SOCS app that allows SOCS events to be imported into Fixtures.',
           (SELECT id FROM lu_config_field WHERE field = 'password'),
           (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT sequence+1 FROM `config` c WHERE c.name = 'socs_endpoint_host')
       );

-- Add new config
INSERT INTO config (`name`, `value`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'socs_endpoint_id',
           '2460', # We don't know if this the same for all schools or not, so make it editable just in case, but pre-populate with the value from our test creds
           'SOCS Endpoint ID',
           'The ID for the URL Auth from the SOCS app that allows SOCS events to be imported into Fixtures.',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT sequence+1 FROM `config` c WHERE c.name = 'socs_endpoint_token')
       );
