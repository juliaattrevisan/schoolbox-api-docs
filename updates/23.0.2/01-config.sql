-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'External DB'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='external_liss_teacher_identifier' LIMIT 1);

-- Add the choice to activate logging to brownhole storage
-- This is a user-selectable field, for now, so schools can decide
-- when they enable it and when they don't

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
   'external_liss_storage_logging',
   '0',
   'External LISS Local Logging',
   'Temporarily log LISS data to local file storage for diagnostic purposes.',
   (SELECT id FROM lu_config_field WHERE field = 'boolean'),
   null,
   (SELECT id FROM lu_config_type WHERE type = 'External DB'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT sequence+1 FROM `config` c WHERE c.name = 'external_liss_teacher_identifier')
);