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