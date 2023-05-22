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