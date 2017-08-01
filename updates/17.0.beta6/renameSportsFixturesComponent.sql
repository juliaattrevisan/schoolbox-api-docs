UPDATE component_config_definition
  JOIN component_definition on component_definition.id = component_definition_id
SET default_value = 'Team Fixtures'
where label = 'Heading' AND class = 'Schoolbox\\Fixtures\\Component\\Homepage\\Controller';

UPDATE component_config_definition
  JOIN component_definition on component_definition.id = component_definition_id
SET default_value = 'My Fixtures'
where label = 'Heading' AND class = 'Schoolbox\\Fixtures\\Component\\Dashboard\\Controller';
