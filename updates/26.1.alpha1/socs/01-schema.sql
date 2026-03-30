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
