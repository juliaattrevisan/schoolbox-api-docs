INSERT INTO component_definition
(
  name,
  class,
  description,
  module,
  component_category,
  visible,
  multiple
)
  SELECT
    'Classroom Learning Snapshot',
    'Custom\\Loyola\\Survey\\Component\\Homepage\\Controller',
    'Provides a summary of latest survey results for all students in the class',
    'homepage',
    3,
    'true',
    'false'
  FROM `config`
  WHERE `name` = 'site_location' AND `value` IN ('loyola', 'demo');

-- Annoyingly a component MUST have at least one configuration option even if there is nothing you
-- want them to configure- otherwise they can't delete the component! So give them a component name to
-- customize for shiggles...

INSERT INTO schoolbox.component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
)
  SELECT
    `id`,
    'componentname',
    'Classroom Learning Snapshow',
    'Heading',
    NULL
  FROM `component_definition`
  WHERE `class` = 'Custom\\Loyola\\Survey\\Component\\Homepage\\Controller'
