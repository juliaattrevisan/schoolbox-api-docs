UPDATE config SET type_id = (SELECT id FROM lu_config_type WHERE type like 'Debugging') WHERE name = 'debug_templates';

UPDATE config SET type_id = (SELECT id FROM lu_config_type WHERE type like 'LMS Policy') WHERE name like 'course_builder_default%';
UPDATE config SET field_id = 1 WHERE field_id IS NULL;

-- set default field "input"
UPDATE config
  LEFT JOIN lu_config_field ON config.field_id = lu_config_field.id
SET config.field_id = 1
WHERE lu_config_field.id IS NULL;

-- set default type "Site"
UPDATE config
  LEFT JOIN lu_config_type ON config.type_id = lu_config_type.id
SET config.type_id = 1
WHERE lu_config_type.id IS NULL;


ALTER TABLE `config`
  ADD CONSTRAINT `fk_config_field_id`
FOREIGN KEY (`field_id`)
REFERENCES `lu_config_field` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_config_type_id`
FOREIGN KEY (`type_id`)
REFERENCES `lu_config_type` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
