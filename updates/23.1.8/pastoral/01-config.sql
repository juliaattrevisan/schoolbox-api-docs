-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
    SET c.sequence = c.sequence + 1
WHERE lct.type = 'Pastoral Care'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='external_pastoral' LIMIT 1);

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
   'external_pastoral_title_include',
   '1',
   'Include pastoral title in SIS writeback',
   "If toggled to 'ON' this will send the pastoral title back to your SIS.",
   (SELECT id FROM lu_config_field WHERE field = 'boolean'),
   null,
   (SELECT id FROM lu_config_type WHERE type = 'Pastoral Care'),
   'true',
   'false',
   'false',
   'settings',
   (SELECT sequence+1 FROM `config` c WHERE c.name = 'external_pastoral')
);
