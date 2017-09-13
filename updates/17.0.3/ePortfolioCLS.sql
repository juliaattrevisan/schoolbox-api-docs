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
