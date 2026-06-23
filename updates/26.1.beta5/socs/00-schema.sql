-- Push down all the other configs in this area
-- Create a hole in config
UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = '3rd Party Services'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='socs_endpoint_host' LIMIT 1);

-- Add new config
INSERT INTO config (`name`, `value`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'socs_endpoint_id',
           '',
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
