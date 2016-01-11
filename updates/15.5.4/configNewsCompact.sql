INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
  SELECT id, 'compactMode', '0', 'Compact Mode', ''
  FROM component_definition
  WHERE class = 'Component_Dashboard_NewsController';

INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
SELECT id, 'compactMode', '0', 'Compact Mode', ''
FROM component_definition
WHERE class = 'Component_Homepage_NewsController';

