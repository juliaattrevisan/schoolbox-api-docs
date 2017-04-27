
DELETE component_config_definition FROM component_config_definition
  LEFT JOIN component_definition ON component_config_definition.component_definition_id = component_definition.id
WHERE component_definition.id is null;


ALTER TABLE `component_config_definition`
  ADD CONSTRAINT `fk_component_config_definition_definition_id`
FOREIGN KEY (`component_definition_id`)
REFERENCES `component_definition` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


DELETE component_config_instance FROM component_config_instance
  LEFT JOIN component_config_definition ON component_config_instance.component_config_definition_id = component_config_definition.id
WHERE component_config_definition.id is null;

ALTER TABLE `component_config_instance`
  ADD CONSTRAINT `fk_component_config_instance_component_config_definition_id`
FOREIGN KEY (`component_config_definition_id`)
REFERENCES `component_config_definition` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE component_config_instance FROM component_config_instance
  LEFT JOIN component_instance ON component_config_instance.component_instance_id = component_instance.id
WHERE component_instance.id is null;

ALTER TABLE `component_config_instance`
  ADD CONSTRAINT `fk_component_config_instance_component_instance_id`
FOREIGN KEY (`component_instance_id`)
REFERENCES `component_instance` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

DELETE component_instance FROM component_instance
  LEFT JOIN component_definition ON component_instance.component_definition_id = component_definition.id
WHERE component_definition.id is null;

ALTER TABLE `component_instance`
  ADD CONSTRAINT `fk_component_instance_component_definition_id`
FOREIGN KEY (`component_definition_id`)
REFERENCES `component_definition` (`id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;