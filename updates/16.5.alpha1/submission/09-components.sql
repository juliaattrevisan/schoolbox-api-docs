ALTER TABLE component_definition MODIFY class VARCHAR(255) NOT NULL;

INSERT INTO component_definition (
  name,
  class,
  description,
  module,
  component_category,
  visible,
  multiple
) VALUES (
  'Tasks',
  'Schoolbox\\Learning\\Assessment\\Task\\Component\\Homepage\\Controller',
  'Output all tasks for this homepage. This allows students to see due dates and submit via the homepage.',
  'homepage',
  '3',
  'true',
  'false'
);

UPDATE component_definition
SET class = 'Schoolbox\\Learning\\Assessment\\Quiz\\Component\\Homepage\\Controller'
WHERE class = 'Component_Homepage_AssessController';

UPDATE component_definition
SET class = 'Schoolbox\\Learning\\Assessment\\DueWork\\Component\\Homepage\\Controller'
WHERE class = 'Component_Homepage_DueWorkListController';

UPDATE component_definition
SET class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Builder\\Controller'
WHERE class = 'Component_Homepage_CourseBuilderController';

UPDATE component_definition
SET class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Outline\\Controller'
WHERE class = 'Component_Homepage_CourseOutlineController';

INSERT INTO component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
  SELECT (SELECT `id` FROM component_definition WHERE class = 'Schoolbox\\Learning\\Assessment\\Task\\Component\\Homepage\\Controller'),
    `key`, `default_value`, `label`, component_config_definition.`description`
  FROM component_config_definition JOIN component_definition ON component_definition.id = component_config_definition.component_definition_id
  WHERE class = 'Schoolbox\\Learning\\Assessment\\DueWork\\Component\\Homepage\\Controller';

